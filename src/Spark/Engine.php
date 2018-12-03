<?php

namespace Spark;

use ErrorException;
use Spark\Cache\ApcuBeanCache;
use Spark\Cache\BeanCache;
use Spark\Cache\Service\CacheProvider;
use Spark\Cache\Service\CacheService;
use Spark\Common\Collection\FluentIterables;
use Spark\Core\Annotation\Handler\EnableApcuAnnotationHandler;
use Spark\Core\Command\Command;
use Spark\Core\Command\Input\InputInterface;
use Spark\Core\Command\Output\OutputInterface;
use Spark\Core\CoreConfig;
use Spark\Core\Error\ExceptionResolver;
use Spark\Core\Error\GlobalErrorHandler;
use Spark\Core\Event\EventBus;
use Spark\Core\Event\Handler\SubscribeAnnotationHandler;
use Spark\Core\Filler\BeanFiller;
use Spark\Core\Filler\CookieFiller;
use Spark\Core\Filler\FileObjectFiller;
use Spark\Core\Filler\MultiFiller;
use Spark\Core\Filler\RequestFiller;
use Spark\Core\Filler\SessionFiller;
use Spark\Core\Filler\SimpleMultiFiller;
use Spark\Core\Filter\FilterChain;
use Spark\Core\Filter\HttpFilter;
use Spark\Core\Interceptor\HandlerInterceptor;
use Spark\Core\Lang\CookieLangKeyProvider;
use Spark\Core\Lang\LangKeyProvider;
use Spark\Core\Lang\LangMessageResource;
use Spark\Core\Lang\LangResourcePath;
use Spark\Core\Library\Annotations;
use Spark\Core\Library\BeanLoader;
use Spark\Core\Processor\InitAnnotationProcessors;
use Spark\Core\Processor\Loader\StaticClassContextLoader;
use Spark\Core\Provider\BeanProvider;
use Spark\Core\Routing\Factory\RequestDataFactory;
use Spark\Core\Routing\RequestData;
use Spark\Core\Routing\RoutingDefinition;
use Spark\Core\Utils\SystemUtils;
use Spark\Form\Validator\AnnotationValidator;
use Spark\Http\Request;
use Spark\Http\RequestProvider;
use Spark\Http\Response;
use Spark\Http\Session\BaseSessionProvider;
use Spark\Http\Session\SessionProviderProxy;
use Spark\Http\Utils\RequestUtils;
use Spark\Routing\RoutingInfo;
use Spark\Utils\Asserts;
use Spark\Utils\BooleanUtils;
use Spark\Utils\Collections;
use Spark\Utils\Functions;
use Spark\Utils\Objects;
use Spark\Utils\Predicates;
use Spark\Utils\ReflectionUtils;
use Spark\Utils\StringUtils;
use Spark\Utils\UrlUtils;
use Spark\View\Json\JsonViewHandler;
use Spark\View\Json\JsonViewModel;
use Spark\View\Plain\PlainViewHandler;
use Spark\View\Redirect\RedirectViewHandler;
use Spark\View\Smarty\SmartyPlugins;
use Spark\View\Smarty\SmartyViewHandler;
use Spark\View\ViewHandlerProvider;
use Spark\View\ViewModel;

class Engine {


    /**
     * @var string Application Name used in apcu Cache as a prefix
     */
    private $appName;

    private $appPath;

    /**
     * @var Routing
     */
    private $route;

    /**
     * App configuration
     * @var Config
     */
    private $config;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var BeanCache
     */
    private $beanCache;

    private $exceptionResolvers;

    private $profile;
    private $contextLoader;


    public function __construct(string $appName, $profile, array $rootAppPath) {
        Asserts::checkState(\extension_loaded('apcu'), 'Apcu Cache enable is mandatory!');
        Asserts::notNull($rootAppPath[0], "Engine configuration: did you forget root project path('s) field: 'root' e.g 'path'");

        $this->appName = $appName;
        $this->profile = $profile;

        $appPaths = $rootAppPath;
        $this->appPath = $appPaths[0];

        $this->beanCache = new ApcuBeanCache();
//        $this->contextLoader = new CacheContextLoader($this->appName, $this->beanCache);
        $this->contextLoader = new StaticClassContextLoader();


        if ($this->contextLoader->hasData()) {
            $this->container = $this->contextLoader->getContainer();
            $this->route = $this->contextLoader->getRoute();
            $this->config = $this->contextLoader->getConfig();
            $this->exceptionResolvers = $this->contextLoader->getExceptionResolvers();

            $resetParam = $this->config->getProperty(EnableApcuAnnotationHandler::APCU_CACHE_RESET);

            if (isset($_GET[$resetParam])) {
                $this->contextLoader->clear();
                $this->beanCache->clearAll();
            }
        }

        if (!$this->contextLoader->hasData()) {
            $this->contextLoader->clear();

            $this->container = new Container();
            $this->route = new Routing(array());
            $this->config = new Config();

            $this->config->set('app.profile', $this->getProfile());
            $this->config->set('app.path', $this->appPath);
            $this->config->set('app.paths', $appPaths);
            $this->config->set('src.path', $this->getSourcePath());

            $initAnnotationProcessors = new InitAnnotationProcessors($this->route, $this->config, $this->container);

            $beanLoader = new BeanLoader($initAnnotationProcessors, $this->config, $this->container);
            foreach ($appPaths as $path) {
                $beanLoader->addFromPath($path . '/src', array('proxy'));
            }

            $beanLoader->addClass(CoreConfig::class);
            $beanLoader->process();

            $this->addBaseServices();
            $this->container->setConfig($this->config);
            $this->container->initServices();

            $beanLoader->postProcess();

            $this->afterAllBean();

            $this->exceptionResolvers = $this->container->getByType(ExceptionResolver::class);

            $this->contextLoader->save(
                $this->config,
                $this->container,
                $this->route,
                $this->exceptionResolvers
            );
        }

        /** @var GlobalErrorHandler $globalErrorHandler */
        $globalErrorHandler = $this->container->get(GlobalErrorHandler::NAME);
        $globalErrorHandler->setup($this->exceptionResolvers);
    }

    public function run(): void {
        if (SystemUtils::isCommandLineInterface()) {
            $this->runCommand();
        } else {
            $this->runController();
        }
    }

    private function runController(): void {
        $this->handleRequest();
    }

    private function handleRequest(array $responseParams = array()): void {
        $this->devToolsInit();

        $registeredHostPath = UrlUtils::getHost();
        $requestData = $this->route->createRequest($registeredHostPath);

        $this->updateRequestProvider($requestData);

        //Interceptor
        $this->executeFilter($requestData);
        $this->preHandleInterceptor($requestData);

        //Controller
        $controllerName = $requestData->getControllerClassName();

        /** @var $controller Controller */
        $controller = $this->container->get($controllerName);

        if ($controller instanceof Controller) {
            $controller->init($requestData, $responseParams);
            $controller->setContainer($this->container);
        }

        //ACTION->VIEW
        $this->handleAction($requestData, $controller);
    }

    private function devToolsInit(): void {
        $enabled = $this->config->getProperty(Config::DEV_ENABLED);
        if ($enabled) {
            RequestUtils::setCookie('XDEBUG_SESSION', true);
        }
    }

    private function addBaseServices(): void {
        $this->register('cache', $this->beanCache);
        $this->container->registerObj(new CacheProvider());
        $this->container->registerObj(new CacheService());

        //EventBus
        $this->container->registerObj(new EventBus());
        $this->container->registerObj(new SubscribeAnnotationHandler());

        $langResource = new LangMessageResource(array());
        $this->register(LangMessageResource::NAME, $langResource);
        $this->register(LangKeyProvider::NAME, new CookieLangKeyProvider('lang'));

        $this->register(SmartyPlugins::NAME, new SmartyPlugins());
        $this->register(RequestProvider::NAME, new RequestProvider());
        $this->register(RoutingInfo::NAME, new RoutingInfo($this->route));
        $this->register('sessionProvider', new SessionProviderProxy());
        $this->register('defaultSessionProvider', new BaseSessionProvider());

        $this->container->registerObj(new RequestDataFactory());
        $this->container->registerObj(new BeanProvider($this->container));

        $validator = new AnnotationValidator($langResource, ReflectionUtils::getReaderInstance());
        $validator->addDefaultValidators();
        $this->container->addBean('annotationValidator', $validator);

        $this->container->registerObj($this->config);
        $this->container->registerObj($this->route);
        $this->container->registerObj(new GlobalErrorHandler($this));

        $this->container->registerObj(new SimpleMultiFiller());
        $this->container->registerObj(new RequestFiller());
        $this->container->registerObj(new SessionFiller());
        $this->container->registerObj(new FileObjectFiller());
        $this->container->registerObj(new CookieFiller());
        $this->container->registerObj(new BeanFiller());

        $this->addViewHandlersToService();
    }

    private function afterAllBean(): void {
        $resourcePaths = $this->container->getByType(LangResourcePath::class);

        /** @var LangMessageResource $resource */
        $resource = $this->container->get(LangMessageResource::NAME);
        $resource->addResources($resourcePaths);

        $sessionProvider = $this->container->get('sessionProvider');
        $this->route->setSessionProvider($sessionProvider);
    }

    private function addViewHandlersToService(): void {
        $smartyViewHandler = new SmartyViewHandler($this->appPath);
        $plainViewHandler = new PlainViewHandler();
        $jsonViewHandler = new JsonViewHandler();
        $redirectViewHandler = new RedirectViewHandler();

        $provider = new ViewHandlerProvider();
        $this->register(ViewHandlerProvider::NAME, $provider);
        $this->register('defaultViewHandler', $smartyViewHandler);
        $this->register(SmartyViewHandler::NAME, $smartyViewHandler);
        $this->register(PlainViewHandler::NAME, $plainViewHandler);
        $this->register(JsonViewHandler::NAME, $jsonViewHandler);
        $this->register(RedirectViewHandler::NAME, $redirectViewHandler);
    }

    /**
     * @param RequestData|Request $requestData
     * @param $controller
     * @throws ErrorException
     */
    private function handleAction(RequestData $requestData, $controller): void {

        /** @var $viewModel ViewModel */
        $methodName = $requestData->getMethodName();
        $routeDef = $requestData->getRouteDefinition();
        $methodFillersParams = $this->executeFillers($routeDef);

        $viewModel = Objects::invokeMethod($controller, $methodName, $methodFillersParams);

        if ($this->isRestController($routeDef)) {
            $viewModel = new JsonViewModel($viewModel);
        }

        $this->handleViewModel($requestData, $viewModel);
    }

    /**
     * @param ViewModel $viewModel
     * @param Request $request
     */
    private function handleView($viewModel, $request): void {
        $handler = $this->container->get(ViewHandlerProvider::NAME);

        Asserts::notNull($handler, 'No handler found for response object' . Objects::getClassName($viewModel));

        /** @var $handler ViewHandlerProvider */
        $handler->handleView($viewModel, $request);
    }


    private function executeFilter(Request $request): void {
        $filters = $this->container->getByType(HttpFilter::class);

        if (Collections::isNotEmpty($filters)) {
            $filtersIterator = new \ArrayIterator($filters);
            $chain = new FilterChain($filtersIterator->current(), $filtersIterator);
            $chain->doFilter($request);
        }
    }


    private function isApcuCacheEnabled(): bool {
        return $this->config->getProperty(EnableApcuAnnotationHandler::APCU_CACHE_ENABLED, false);
    }

    private function preHandleInterceptor(Request $request): void {
        $interceptors = $this->container->getByType(HandlerInterceptor::class);

        /** @var HandlerInterceptor $interceptor */
        foreach ($interceptors as $interceptor) {
            if (BooleanUtils::isFalse($interceptor->preHandle($request))) {
                break;
            }
        }
    }

    private function postHandleIntercetors(Request $request, Response $response): void {
        $interceptors = $this->container->getByType(HandlerInterceptor::class);

        /** @var HandlerInterceptor $interceptor */
        foreach ($interceptors as $interceptor) {
            $interceptor->postHandle($request, $response);
        }

    }

    /**
     *
     * @param Request $request
     * @param ViewModel $viewModel
     * @throws ErrorException
     * @throws Common\IllegalStateException
     */
    public function handleViewModel(Request $request, $viewModel): void {
        Asserts::checkState($viewModel instanceof Response, 'Wrong controller action response type. Returned type from controller needs to be instance of Response.');

        $this->postHandleIntercetors($request, $viewModel);

        if (Objects::isNotNull($viewModel)) {
            $this->handleView($viewModel, $request);
        } else {
            throw new ErrorException('ViewModel not found. Did you initiated ViewModel? ');
        }
    }

    /**
     *
     * @param $request
     * @throws \Exception
     */
    public function updateRequestProvider(Request $request): void {
        /** @var RequestProvider $requestProvider */
        $requestProvider = $this->container->get(RequestProvider::NAME);
        $requestProvider->setRequest($request);
    }

    private function runCommand(): void {
        $input = new InputInterface();
        $out = new OutputInterface();

        $commands = $this->container->getByType(Command::class);
        Collections::builder($commands)
            ->filter(Predicates::compute(Functions::get('name'), function ($n) use ($input) {
                return StringUtils::startsWith($n, $input->get('command'));
            }))
            ->each(function ($command) use ($input, $out) {
                /** @var Command $command */
                $command->execute($input, $out);
            });
    }


    private function getProfile(): ?string {
        if (SystemUtils::isCommandLineInterface()) {
            return SystemUtils::getProfile();
        }
        return $this->profile;
    }

    private function executeFillers(RoutingDefinition $rf): array {
        $parameters = $rf->getActionMethodParameters();
        $simpleFiller = $this->container->getByType(MultiFiller::class);


        $tmpParameters = $parameters;

        $results = [];
        /** @var MultiFiller $filler */
        foreach ($simpleFiller as $filler) {
            $filedParams = $filler->filter($tmpParameters);

            $newParams  = [];
            foreach ($filedParams as $k => $filedParam) {

                if (Objects::isNotNull($filedParam) && !Collections::hasKey($results, $k)) {
                    $results[$k] = $filedParam;
                } else {
                    $newParams[$k]=$parameters[$k];
                }
            }
            $tmpParameters = $newParams;
        }

        return FluentIterables::of(Collections::getKeys($parameters))
            ->map(function($key) use ($results) {
                return Collections::getValue($results, $key);
            })->getList();
    }

    private function isRestController(RoutingDefinition $routeDef): bool {
        $controllerAnnotations = $routeDef->getControllerAnnotations();

        $restControllerAnnotation = Collections::builder()
            ->addAll($controllerAnnotations)
            ->findFirst(function ($ann) {
                return Objects::getClassName($ann) === Annotations::REST_CONTROLLER;
            });

        return $restControllerAnnotation->isPresent();
    }

    /**
     * @return string
     */
    private function getSourcePath(): string {
        return $this->appPath . '/src';
    }

    private function register($beanName, $class) {
        $this->container->addBean($beanName, $class, false, true);
    }


}
