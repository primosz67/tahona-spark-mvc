<?php
/**
 *
 *
 * Date: 11.04.15
 * Time: 17:14
 */

namespace Spark\Core\Error;


use ErrorException;
use Spark\Container;
use Spark\Core\Annotation\Inject;
use Spark\Core\Annotation\PostConstruct;
use Spark\Core\Provider\BeanProvider;
use Spark\Core\Routing\Factory\RequestDataFactory;
use Spark\Core\Routing\RequestData;
use Spark\Engine;
use Spark\Http\HttpCode;
use Spark\Http\Request;
use Spark\Http\ResponseHelper;
use Spark\Utils\Collections;
use Spark\Utils\Objects;

class GlobalErrorHandler {

    public const NAME = 'globalErrorHandler';
    public const EXCEPTION_HANDLER = 'handleException';
    public const ERROR_HANDLER = 'handleError';
    public const FATAL_HANDLER = 'handleFatal';

    /**
     * @var Engine
     */
    private $engine;
    private $exceptionResolvers;

    /**
     * @Inject()
     * @var RequestDataFactory
     */
    private $requestDataFactory;

    public function setup(Engine $engine, $resolvers = array()) {
        $this->engine = $engine;
        $this->exceptionResolvers = $resolvers;
        set_exception_handler(array($this, self::EXCEPTION_HANDLER));
        set_error_handler(array($this, self::ERROR_HANDLER));
//        register_shutdown_function(array($this, self::FATAL_HANDLER));
    }

    /**
     *
     * @param $exception \Exception
     * @throws \Exception
     */
    public function handleException($exception) {
        $errorReporting = error_reporting();

        if ($errorReporting == 0) {
            return;
        } else if ($errorReporting) {
            $invoke = $this->getHandler();
            $err = $invoke($exception);

            if (Objects::isNotNull($err)) {
                throw $err;
            }
            return;
        }
    }


    public function handleError($severity, $message, $filename, $lineno) {
        $error = error_get_last();
        $errorReporting = error_reporting();

        if ($errorReporting == 0) {
            return;
        }

        if ($errorReporting && Objects::isNotNull($error)) {
            $errorException = $this->handleErrorAction($error);
            $this->handleException($errorException);
        }
    }


    public function handleFatal() {
        $error = error_get_last();

        if ($error['type'] == E_ERROR && error_reporting() && Objects::isNotNull($error)) {
            $errorException = $this->handleErrorAction($error);
            $this->handleException($errorException);
            return;
        }
    }


    private function getHandler() {
        return function ($error) {

            $exceptionResolvers = Collections::stream($this->exceptionResolvers)
                ->sort(function ($x, $y) {
                    /** @var ExceptionResolver $x */
                    return $x->getOrder() > $y->getOrder();
                })
                ->get();

            foreach ($exceptionResolvers as $resolver) {
                /** @var ExceptionResolver $resolver */
                $viewModel = $resolver->doResolveException($error);
                if (Objects::isNotNull($viewModel)) {
                    $request = $this->requestDataFactory->createRequestData();
                    $this->engine->updateRequestProvider($request);
                    $this->engine->handleViewModel($request, $viewModel);

                    return null;
                }
            }

            //Default behavior
            /** @var ErrorException $error */
            ResponseHelper::setCode(HttpCode::INTERNAL_SERVER_ERROR);
            return new \Exception($error->getMessage(), $error->getCode(), $error);
        };
    }

    private function handleErrorAction($error) {
        $severity = $error['type'];
        $filename = $error['file'];
        $lineno = $error['line'];
        $message = $error['message'];

        return new \ErrorException($message, 0, $severity, $filename, $lineno);
    }


}