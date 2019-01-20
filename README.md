
# README #

### What is this repository for? ###

Very fast PHP framework with autoloading and annotations. All configuration is handled by annotations and beans.



#### How it works  ####
Framework automatically load beans from app/src path it just need to be annotated with one annotation of: **@Component, @Service, @Configuration or @Repository**.

Bean injection can be achieved with **@Inject** annotation.

Initialization is done on the first request. The second request execute Controllers  action, that has  already injected beans in it.

Request execution time is very small because it's time of user code execution only.

Thanks to this,  for real production code we got **20ms-40ms** per request for dynamic data.


### Quick Start ###

For quick start donwload example project: https://github.com/primosz67/spark-mvc-example

### Index php - explained ###

app/public/index.php

```php
error_reporting(E_ALL);

define("__ROOT__", __DIR__ . "/../../");
define("__VENDOR__", "../../vendor");

require __VENDOR__ . "/autoload.php";
```

Note:
If you will use doctrine db framework add here line - "AnnotationRegistry::registerLoader('class_exists');"

Framework setup:
```php
$profileName ="someNameProfile";
$engine = new Engine("przemek_config", $profileName, __ROOT__ . "app");
$engine->run();

```
### Configuration ###
app/src/MyAppConfig.php

```php
/**
 * @Configuration()
 * @EnableApcuBeanCache(resetParam="reset")
 */
class MyAppConfig {
}
```

* resetParam - parameter for clearing cache. (GET http://app.com?reset).
It's for development environment and should be deleted for production.


### Controller ###

##### 1. Standard Controller with annotation controller #####

app/src/MyAppController.php

```php
/**
* @Controller
*/
class MyAppController {

    /**
     * @Path("/index", method="GET")
     */
    public function indexAction(Request $request) {
        return new PlainViewModel("Hello World");
    }

   /**
     * @Path("/save", method="POST")
     */
    public function saveAction(SomeForm $form, Request $req, $beanName) {
        return new JsonViewModel(array(
            "user"=>"TODO"
        ));
    }
}
```

Go to localhost/get or localhost/index;

* Request is injected to method by Type
* SomeForm is filled by "POST" form params using "spark-form" module.
* $beanName - framework can inject any bean or service needed for action by name or type.


##### 2. Rest Controller #####

```php
/**
* @RestController
*/
class MyRestController {

    /**
     * @Path("/index")
     */
    public function indexAction(Request $request) {
        return new SomeResultDto();
    }
}
```

Note: All methods in RestController will resolve to JSON.

SomeResultDto - will resolve to json.

##### 3. Old method of creating Controller #####

app/src/MyAppController.php

```php
class MyAppController extends Controller {


    /**
     * @Path("/index")
     */
    public function indexAction() {
        return new PlainViewModel("Hello World");
    }

    /**
     * @Path("/get")
     */
    public function getAction() {
        return new JsonViewModel(array(
            "user"=>"TODO"
        ));
    }

    /**
     * @Path("/newView")
     */
     public function showNewViewAction() {
        return new ViewModel(array(
            "user"=>"TODO"
        ));
     }

}
```

Go to localhost/get or localhost/index;

##### 2. Define bean for autoload. #####

### Injection ###

##### 1. Define bean for autoload. #####

```php

/**
* @Service()
*/
class UserService {
    //...some methods
}
```
Note: for enabled apcu @EnableApcuBeanCache("reset") to autoload injection, call http://website?reset


##### 2. Define other beans and inject out bean. #####
```php

/**
* @Component()
*/
class AddUserHandler {

    /**
    * @Inject()
    * var UserService
    */
    private $userService;

     /**
     * @PostConstruct()
     */
     public function init() {
        $this->userService->doSomething();
     }
}
```

##### 3.Inject in controller #####

```php
class UserController extends Controller {

    /**
    * @Inject()
    * var UserService
    */
    private $userService;

    /**
     * @Path("/newView")
     */
     public function showNewViewAction() {
        return new ViewModel(array(
            "users"=>$this->userService->getAllUsers()
        ));
     }
}
```
##### 4.Inject in action  method of controller #####

```php
/**
* @Controller
*/
class UserController {

    /**
     * @Path("/newView")
     */
     public function showNewViewAction(Request $request, UserService $userService ) {
        return new ViewModel(array(
            "users"=>$userService->getAllUsers()
        ));
     }
}
```


### View ###

apc/view/{controller package}/{controllerName (without "Controller")}/{action}.tpl

1. For app/src/MyAppController@showNewViewAction we get:
   apc/view/myapp/showNewView.tpl
2. For app/src/some/serious/package/*controller*/MyAppController@showNewView*Action* we get:
apc/view/some/serious/package/myapp/showNewView.tpl

Keywords action and controller are deleted by default.

#### Smarty built-in path plugins ####

Function path can be used in tpl file (example: index.tpl)

```
{path path="/user/register"}
```

Command will resolve to : www.example.com/user/register

```
{path path="RegisterController@registerAction"}
```

Command will resolve to : www.example.com/user/register
* **com.example.RegisterController** - optional Controller class Name
* **registerAction** - method name in RegisterController

```
{path path="@removeAction@id:4,type:blogpost"}
```

Command will resolve to : www.example.com/blogpost/remove/4
* **{controller}** - current request controller will be used
* **removeAction** - method name in RegisterController
* **id:4,type:blogpost** - params will resolve /{type}/remove/{id} to it's final form

### Apcu Bean Cache ###
if @EnableApcuBeanCache annotation is added with @Configuration the only way to reset beans and init them
once more is by requestin localhost:80?reset (GET parameter "reset").


### Mailer ###
* @EnableMailer -TODO
* spark.mailer.enabled (true/false)- property


### @Annotations ###
The heart of Spark Framework.

* @Component,@Service,@Repository,@Configuration - do the same thing, but purpose is the key.
* @Bean
* @PostConstruct
* @Inject
* @Bean

### Application Parameters ###
$this->config

Base parameters:
app.path - pierwsza ścieżka do katalogu /app
src.path - ścieżka do katalogu /app/src
app.paths - ścieżka albo ścieżki do katalogu /app w różnych kontekstach

to fetch parameters:
```php
 $appPath = $this-config->getProperty("app.path");
```

update or set Params
```php
 $this-config->setProperty("customModule.some.property.", "/my/new/path");
```

### Custom module loading ###
If you create common module to use in other project remember to create beans by @Bean annotation.
It will be easier to add new module in one go.

In some your app configuration add others OtherModuleConfig.

```php
/**
* @Bean
*/
public function otherBeanConfiguration () {
    return new OtherModuleConfig();
}
```
All @Bean annotation in OtherModuleConfig will be created and inject to your classes.

### Multiple DataBase connection (example) ###

Handle multiple connections.
To create Doctrine's EntityManager you can use simple EntityManagerFactory.

```php

    /**
     * @Inject()
     * @var EntityManagerFactory
     */
    private $entityManagerFactory;


    /**
    * @Bean()
    */
    public function entityManager() {
        return $this->entityManagerFactory->createEntityManager($this->getDataSource());
    }

    /**
    * @Bean()
    */
    public function superEntityManager() {
        return $this->entityManagerFactory->createEntityManager($this->getDataSourceSuper());
    }

    public function getDataSource() {
        $dbConfig = new DataSource();
        $dbConfig->setDbname("my-db");
        $dbConfig->setHost("127.0.0.1");
        $dbConfig->setUsername("root");
        $dbConfig->setPassword("test");
        $dbConfig->setPackages([
            "com/myapp/user/domain" //path to doctrine entity
        ]);
        return $dbConfig;
    }

    public function getDataSourceSuper() {
        $dbConfig = new DataSource();
        $dbConfig->setDbname("super");
        $dbConfig->setHost("127.0.0.1");
        $dbConfig->setUsername("root");
        $dbConfig->setPassword("test");
        return $dbConfig;
    }
```

Note: for using CrudDao with other enityManager than basic use @OverrideInject annotation

```php
/**
* @OverrideInject(oldName="entityManager", newName="houseManager")
*/
class MySuperDao extends CrudDao {
}

```
### Internalization ###

Bean definition
```php

    /**
    * @Bean
    */
    public function anyPrefixNameMessageResource() {
        return new LangResourcePath(array(
            "pl"=>array(
                "/house/house_pl.properties"
            ),
            "cz"=> array(...),
            "en"=>array(...),
        ));
    }
```

Where "pl","cz","en" are cookie value with key "lang";

* Property file
/house/house_pl.properties

```
core.thank.you.message=Thank You {0} and {1}
```

* Use in php
```php
    /**
     * @Inject
     * @var langMessageResource
     */
    private $langMessageResource;


    ...

    $this->langMessageResource->get("core.thank.you.message", array("John", "Trevor"));

```

* Use in smarty

```
{lang code="core.thank.you.message" value=["John", "Trevor"]}
```
Results: Thank You John and Trevor

### @Path ###

Annotation Defonition


```php
@Path(path="/login/{test}", method="get")

```

Fetch path paramether in Controller class;
```php
$this->getParam("test");

```

### MULTI fetch by component   ###

Example is for dynamic menu module that will popup when new project or classes are added.
```php

    /**
    * @Inject
    */
    private $beanProvider;

    public function getMenuModules() {
        return $beanProvider->getByType(MenuModule::class);
    }
```

### Interceptors ###

```php
/**
* @Component
*/
class UserInterceptor implements HandlerInterceptor {

    /**
    * @Inject
    */
    private $someUserHolder;

    public function preHandle(Request $request) {
        //Do something like redirect or add values to request or auto-conversion(id to entity);
    }

    public function postHandle(Request $request, ViewModel $viewModel) {
        $viewModel->add("loggedUser", $someUserHolder->getUserFromSession())
    }
}
```

### Command behaviour for PHP cli ###

First, create class Command implementation

```php
/**
 * @Component()
 */
class ExampleCommand implements Command {

    /**
     * @Inject()
     * @var SomeBean
     */
    private $someBean;

    public function getName() :string{
        return "example:exampleCommandCommand";
    }

    public function execute(InputInterface $in, OutputInterface $out) : void {
        $out->writeln("executing " . $this->getName());

        //Example ....
        $this->someBean->doSomething()
        $out->writeObject($this->someBean->getSomething());


        $out->writeln("finish!");
    }
}

```
in console execute:

```
php app/public/index.php command=example:exampleCommand profile=production
```


Output:
```
executing example:exampleCommand

object(...)

finish!
```
### Built-in cache service ###

Great thing for caching DB request or loading files data.
Annotation can be used with different cache.
Even custom cache bean that implement spark/cache/Cache

##### How To #####
In Bean class add @Cache annotation.

```php
/**
 * @Cache(name="cache", key="user {0}.id", time=10)
 */
public function getLeaderByCompany($company){
    return $this->someDao->getByCompanyId($company->getId())
}

```

- "name" is a name of bean that implement spark\cache\Cache interface.
- ApcCache needed for application is added as default name="cache"
- "time" - optional parameter that is in minutes(10 minutes)
- "key" parameter is for distinguish cached values



### Profiles ###

```php
$profileName = "production";
$engine = new Engine("przemek_config",$profileName,  __ROOT__ . "app");
```

```php
@Configuration
@Profile(name="production")
class SomeProductionConfig(){
..
}

@Configuration
@Profile(name="development")
class SomeDevelopmentConfig(){
..
}

```

In this case SomeDevelopmentConfig won't be added to container and bean declared in it (@Bean) as well.


### Error handling - example ###

```php
class NotFoundErrorHandler extends ExceptionResolver {

    public function doResolveException($ex) {
        if ($ex instanceof RouteNotFoundException || $ex instanceof EntityNotFoundException) {
            ResponseHelper::setCode(HttpCode::$NOT_FOUND);

            $viewModel = new ViewModel();
            $viewModel->setViewName("/house/web/error/notFound");
            return $viewModel;
        }
        return null;
    }

    public function getOrder() {
        return 400;
    }
}
```

where error handler with order equal 0 , will be first to invoke.
If you return *Viewmodel* the handling will stop and the view will be return as response.

### Event Bus ### 

```php
/**
* @Inject
*/
private $eventBus;
...
$this->eventBus->post(new SomeEvent());

```
Event definition :

```php
class SomeEvent implements Event {...}
```

Subscription
```php

@Component 
class SomeListener {

  /**
  * @Subscribe
  */
  public function handleSomeCase(SomeEvent $event) {
     //...logic
  }

}


```

### AnnotationHandler ### 
Add Your own annotation 


``` 
class NewAnnotationHandler extends AnnotationHandler{
    ...
}

```



### Installation - Composer - Speed up ###


```
composer dump-autoload -a
```


### Performance - Some numbers ###

**Tested Case**: Real project with small database. AB(Apache benchmark) requests 10000 and 1000 parallel connections.

* **Smarty compile**:  Smarty template rendering for each request (Development mode).
* **Smarty**: smarty with production setup.
* **Smarty + @Cache (Redis)**: request to database are cached with @Cache annotation (we use Redis for caching). It can give big improvement to performance when there is more calls to DB.

|Mode| Request time (per request)|
|----| ------------- |
|Smarty compile| ~130ms|
|Smarty | ~45ms  |
|Smarty + @Cache (Redis) | **~25ms** |

### Installation - Composer ###

```
{
    "autoload": {
        "psr-4": {"": "app/src/"}
	},

	"require": {
        "smarty/smarty": "3.1.27",
        "tahona/spark-mvc": "*"
    }
}
```

