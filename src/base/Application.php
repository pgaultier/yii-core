<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use yii\base\ErrorHandler;
use yii\di\Initiable;
use yii\exceptions\ExitException;
use yii\exceptions\InvalidConfigException;
use yii\exceptions\InvalidArgumentException;
use yii\web\Session;
use yii\web\User;
use yii\profile\ProfilerInterface;

/**
 * Application is the base class for all application classes.
 *
 * For more details and usage information on Application, see the [guide article on applications](guide:structure-applications).
 *
 * @property \yii\web\AssetManager $assetManager The asset manager application component. This property is
 * read-only.
 * @property \yii\rbac\ManagerInterface $authManager The auth manager application component. Null is returned
 * if auth manager is not configured. This property is read-only.
 * @property string $basePath The root directory of the application.
 * @property \yii\caching\CacheInterface $cache The cache application component. Null if the component is not
 * enabled. This property is read-only.
 * @property array $container Values given in terms of name-value pairs. This property is write-only.
 * @property \yii\db\Connection $db The database connection. This property is read-only.
 * @property \yii\web\ErrorHandler|\yii\console\ErrorHandler $errorHandler The error handler application
 * component. This property is read-only.
 * @property \yii\i18n\Formatter $formatter The formatter application component. This property is read-only.
 * @property \yii\i18n\I18N $i18n The internationalization application component. This property is read-only.
 * @property \PSR\Log\LoggerInterface $logger The logger. This property is read-only.
 * @property \yii\profile\ProfilerInterface $profiler The profiler. This property is read-only.
 * @property \yii\mail\MailerInterface $mailer The mailer application component. This property is read-only.
 * @property \yii\web\Request|\yii\console\Request $request The request component. This property is read-only.
 * @property \yii\web\Response|\yii\console\Response $response The response component. This property is
 * read-only.
 * @property string $runtimePath The directory that stores runtime files. Defaults to the "runtime"
 * subdirectory under [[basePath]].
 * @property \yii\base\Security $security The security application component. This property is read-only.
 * @property string $timeZone The time zone used by this application.
 * @property string $uniqueId The unique ID of the module. This property is read-only.
 * @property \yii\web\UrlManager $urlManager The URL manager for this application. This property is read-only.
 * [[basePath]].
 * @property View|\yii\web\View $view The view application component that is used to render various view
 * files. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Application extends Module implements Initiable
{
    /**
     * Application state used by [[state]]: application just started.
     */
    const STATE_BEGIN = 0;
    /**
     * Application state used by [[state]]: application is initializing.
     */
    const STATE_INIT = 1;
    /**
     * Application state used by [[state]]: application is triggering [[RequestEvent::BEFORE]].
     */
    const STATE_BEFORE_REQUEST = 2;
    /**
     * Application state used by [[state]]: application is handling the request.
     */
    const STATE_HANDLING_REQUEST = 3;
    /**
     * Application state used by [[state]]: application is triggering [[RequestEvent::AFTER]].
     */
    const STATE_AFTER_REQUEST = 4;
    /**
     * Application state used by [[state]]: application is about to send response.
     */
    const STATE_SENDING_RESPONSE = 5;
    /**
     * Application state used by [[state]]: application has ended.
     */
    const STATE_END = 6;

    /**
     * @var string the namespace that controller classes are located in.
     * This namespace will be used to load controller classes by prepending it to the controller class name.
     * The default namespace is `app\controllers`.
     *
     * Please refer to the [guide about class autoloading](guide:concept-autoloading.md) for more details.
     */
    public $controllerNamespace = 'app\\controllers';
    /**
     * @var string the application name.
     */
    public $name = 'My Application';
    /**
     * @var string the charset currently used for the application.
     */
    public $charset = 'UTF-8';
    /**
     * @var string the language that is meant to be used for end users. It is recommended that you
     * use [IETF language tags](http://en.wikipedia.org/wiki/IETF_language_tag). For example, `en` stands
     * for English, while `en-US` stands for English (United States).
     * @see sourceLanguage
     */
    public $language = 'en-US';
    /**
     * @var string the language that the application is written in. This mainly refers to
     * the language that the messages and view files are written in.
     * @see language
     */
    public $sourceLanguage = 'en-US';
    /**
     * @var Controller the currently active controller instance
     */
    public $controller;
    /**
     * @var string|bool the layout that should be applied for views in this application. Defaults to 'main'.
     * If this is false, layout will be disabled.
     */
    public $layout = 'main';
    /**
     * @var string the requested route
     */
    public $requestedRoute;
    /**
     * @var Action the requested Action. If null, it means the request cannot be resolved into an action.
     */
    public $requestedAction;
    /**
     * @var array the parameters supplied to the requested action.
     */
    public $requestedParams;
    /**
     * @var array list of installed Yii extensions. Each array element represents a single extension
     * with the following structure:
     *
     * ```php
     * [
     *     'name' => 'extension name',
     *     'version' => 'version number',
     *     'bootstrap' => 'BootstrapClassName',  // optional, may also be a configuration array
     *     'alias' => [
     *         '@alias1' => 'to/path1',
     *         '@alias2' => 'to/path2',
     *     ],
     * ]
     * ```
     *
     * The "bootstrap" class listed above will be instantiated during the application
     * [[bootstrap()|bootstrapping process]]. If the class implements [[BootstrapInterface]],
     * its [[BootstrapInterface::bootstrap()|bootstrap()]] method will be also be called.
     *
     * If not set explicitly in the application config, this property will be populated with the contents of
     * `@vendor/yiisoft/extensions.php`.
     */
    public $extensions;
    /**
     * @var array list of components that should be run during the application [[bootstrap()|bootstrapping process]].
     *
     * Each component may be specified in one of the following formats:
     *
     * - an application component ID as specified via [[components]].
     * - a module ID as specified via [[modules]].
     * - a class name.
     * - a configuration array.
     * - a Closure
     *
     * During the bootstrapping process, each component will be instantiated. If the component class
     * implements [[BootstrapInterface]], its [[BootstrapInterface::bootstrap()|bootstrap()]] method
     * will be also be called.
     */
    public $bootstrap = [];
    /**
     * @var int the current application state during a request handling life cycle.
     * This property is managed by the application. Do not modify this property.
     */
    public $state;
    /**
     * @var array list of loaded modules indexed by their class names.
     */
    public $loadedModules = [];

    protected $request;

    protected $response;

    /**
     * Constructor.
     * @param array $config name-value pairs that will be used to initialize the object properties.
     * Note that the configuration must contain both [[id]] and [[basePath]].
     * @throws InvalidConfigException if either [[id]] or [[basePath]] configuration is missing.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->app = $this;

        $this->_container = $container;

        $this->state = self::STATE_BEGIN;
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        $this->state = self::STATE_INIT;
        $this->bootstrap();
    }

    /**
     * Initializes extensions and executes bootstrap components.
     * This method is called by [[init()]] after the application has been fully configured.
     * If you override this method, make sure you also call the parent implementation.
     */
    public function bootstrap(): void
    {
        foreach ($this->bootstrap as $mixed) {
            $component = null;
            if ($mixed instanceof \Closure) {
                $this->debug('Bootstrap with Closure', __METHOD__);
                if (empty($component = call_user_func($mixed, $this))) {
                    continue;
                }
            } elseif (is_string($mixed)) {
                if ($this->has($mixed)) {
                    $component = $this->get($mixed);
                } elseif ($this->hasModule($mixed)) {
                    $component = $this->getModule($mixed);
                } elseif (strpos($mixed, '\\') === false) {
                    throw new InvalidConfigException("Unknown bootstrapping component ID: $mixed");
                }
            }

            if (!isset($component)) {
                $component = $this->createObject($mixed);
            }

            if ($component instanceof BootstrapInterface) {
                $this->debug('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                $component->bootstrap($this);
            } else {
                $this->debug('Bootstrap with ' . get_class($component), __METHOD__);
            }
        }
    }

    public function getApp(): self
    {
        return $this;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->_container;
    }

    public function getRequest()
    {
        if ($this->request === null) {
            $this->request = $this->get('request');
        }

        return $this->request;
    }

    public function getResponse()
    {
        if ($this->response === null) {
            $this->response = $this->get('response');
        }

        return $this->response;
    }

    /**
     * Translates a message to the specified language.
     *
     * This is a shortcut method of [[\yii\i18n\I18N::translate()]].
     *
     * The translation will be conducted according to the message category and the target language will be used.
     *
     * You can add parameters to a translation message that will be substituted with the corresponding value after
     * translation. The format for this is to use curly brackets around the parameter name as you can see in the following example:
     *
     * ```php
     * $username = 'Alexander';
     * echo \Yii::t('app', 'Hello, {username}!', ['username' => $username]);
     * ```
     *
     * Further formatting of message parameters is supported using the [PHP intl extensions](http://www.php.net/manual/en/intro.intl.php)
     * message formatter. See [[\yii\i18n\I18N::translate()]] for more details.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`). If this is null, the current language will be used.
     * @return string the translated message.
     */
    public function t($category, $message, $params = [], $language = null)
    {
        return $this->get('i18n')->translate($category, $message, $params, $language ?: $this->language);
    }

    /**
     * Logs a debug message.
     * Trace messages are logged mainly for development purpose to see
     * the execution work flow of some code.
     * @param mixed $message the message to be logged.
     * @param string $category the category of the message.
     */
    public function debug($message, string $category = 'application'): void
    {
        $this->log(LogLevel::DEBUG, $message, $category);
    }

    /**
     * Logs an error message.
     * An error message is typically logged when an unrecoverable error occurs
     * during the execution of an application.
     * @param string|array $message the message to be logged.
     * @param string $category the category of the message.
     */
    public function error($message, string $category = 'application'): void
    {
        $this->log(LogLevel::ERROR, $message, $category);
    }

    /**
     * Logs a warning message.
     * A warning message is typically logged when an error occurs while the execution
     * can still continue.
     * @param string|array $message the message to be logged.
     * @param string $category the category of the message.
     */
    public function warning($message, string $category = 'application'): void
    {
        $this->log(LogLevel::WARNING, $message, $category);
    }

    /**
     * Logs an informative message.
     * An informative message is typically logged by an application to keep record of
     * something important (e.g. an administrator logs in).
     * @param string|array $message the message to be logged.
     * @param string $category the category of the message.
     */
    public function info($message, string $category = 'application'): void
    {
        $this->log(LogLevel::INFO, $message, $category);
    }

    /**
     * Logs given message through `logger` service.
     *
     * @param string $level log level.
     * @param mixed $message the message to be logged. This can be a simple string or a more
     * complex data structure, such as array.
     * @param string $category the category of the message.
     * @since 3.0.0
     */
    public function log(string $level, $message, $category = 'application'): void
    {
        $this->getLogger()->log($level, $message, ['category' => $category]);
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->get('logger');
    }

    /**
     * Marks the beginning of a code block for profiling.
     *
     * This has to be matched with a call to [[endProfile]] with the same category name.
     * The begin- and end- calls must also be properly nested. For example,
     *
     * ```php
     * \Yii::beginProfile('block1');
     * // some code to be profiled
     *     \Yii::beginProfile('block2');
     *     // some other code to be profiled
     *     \Yii::endProfile('block2');
     * \Yii::endProfile('block1');
     * ```
     * @param string $token token for the code block
     * @param string $category the category of this log message
     * @see endProfile()
     */
    public function beginProfile(string $token, string $category = 'application'): void
    {
        $this->getProfiler()->begin($token, ['category' => $category]);
    }

    /**
     * Marks the end of a code block for profiling.
     *
     * @param string $token token for the code block
     * @param string $category the category of this log message
     * @see beginProfile()
     */
    public function endProfile(string $token, string $category = 'application'): void
    {
        $this->getProfiler()->end($token, ['category' => $category]);
    }

    /**
     * @return ProfilerInterface
     */
    public function getProfiler(): ProfilerInterface
    {
        return $this->get('profiler');
    }

    /**
     * @return ErrorHandler
     */
    public function getErrorHandler(): ErrorHandler
    {
        return $this->get('errorHandler');
    }

    /**
     * @return View
     */
    public function getView()
    {
        return $this->get('view');
    }

    /**
     * @return View
     */
    public function getFormatter()
    {
        return $this->get('formatter');
    }

    /**
     * @return Security
     */
    public function getSecurity()
    {
        return $this->get('security');
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->get('session');
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->get('user');
    }

    /**
     * Returns an ID that uniquely identifies this module among all modules within the current application.
     * Since this is an application instance, it will always return an empty string.
     * @return string the unique ID of the module.
     */
    public function getUniqueId()
    {
        return '';
    }

    /**
     * Sets the root directory of the application and the @app alias.
     * This method can only be invoked at the beginning of the constructor.
     * @param string $path the root directory of the application.
     * @property string the root directory of the application.
     * @throws InvalidArgumentException if the directory does not exist.
     */
    public function setBasePath($path)
    {
        parent::setBasePath($path);
        $this->setAlias('@app', $this->getBasePath());
        if (empty($this->getAlias('@root', false))) {
            $this->setAlias('@root', dirname(__DIR__, 5));
        }
    }

    /**
     * Runs the application.
     * This is the main entrance of an application.
     * @return int the exit status (0 means normal, non-zero values mean abnormal)
     */
    public function run()
    {
        if (YII_ENABLE_ERROR_HANDLER) {
            $this->get('errorHandler')->register();
        }

        try {
            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(RequestEvent::BEFORE);

            $this->state = self::STATE_HANDLING_REQUEST;
            $this->response = $this->handleRequest($this->getRequest());

            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(RequestEvent::AFTER);

            $this->state = self::STATE_SENDING_RESPONSE;
            $this->response->send();

            $this->state = self::STATE_END;

            $this->flushLogger();

            return $this->response->exitStatus;
        } catch (ExitException $e) {
            $this->end($e->statusCode, $this->response ?? null);
            return $e->statusCode;
        }
    }

    /**
     * Handles the specified request.
     *
     * This method should return an instance of [[Response]] or its child class
     * which represents the handling result of the request.
     *
     * @param Request $request the request to be handled
     * @return Response the resulting response
     */
    abstract public function handleRequest($request);

    private $_runtimePath;

    /**
     * TODO: remove completely, use alias instead.
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files.
     * Defaults to the "runtime" subdirectory under [[basePath]].
     */
    public function getRuntimePath()
    {
        if ($this->_runtimePath === null) {
            $this->_runtimePath = $this->getAlias('@runtime');
        }

        return $this->_runtimePath;
    }

    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     */
    public function setRuntimePath($path)
    {
        $this->_runtimePath = $this->getAlias($path);
        $this->setAlias('@runtime', $this->_runtimePath);
    }


    /**
     * Returns the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_get().
     * If time zone is not configured in php.ini or application config,
     * it will be set to UTC by default.
     * @return string the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-get.php
     */
    public function getTimeZone()
    {
        return date_default_timezone_get();
    }

    /**
     * Sets the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_set().
     * Refer to the [php manual](http://www.php.net/manual/en/timezones.php) for available timezones.
     * @param string $value the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-set.php
     */
    public function setTimeZone($value)
    {
        date_default_timezone_set($value);
    }


    /**
     * Terminates the application.
     * This method replaces the `exit()` function by ensuring the application life cycle is completed
     * before terminating the application.
     * @param int $status the exit status (value 0 means normal exit while other values mean abnormal exit).
     * @param Response $response the response to be sent. If not set, the default application [[response]] component will be used.
     * @throws ExitException if the application is in testing mode
     */
    public function end($status = 0, $response = null)
    {
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(RequestEvent::AFTER);
        }

        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $this->response = $response ?: $this->getResponse();
            $this->response->send();
        }

        if (YII_ENV_TEST) {
            throw new ExitException($status);
        }

        $this->flushLogger();

        exit($status);
    }

    protected $aliases = [];

    /**
     * Translates a path alias into an actual path.
     *
     * The translation is done according to the following procedure:
     *
     * 1. If the given alias does not start with '@', it is returned back without change;
     * 2. Otherwise, look for the longest registered alias that matches the beginning part
     *    of the given alias. If it exists, replace the matching part of the given alias with
     *    the corresponding registered path.
     * 3. Throw an exception or return false, depending on the `$throwException` parameter.
     *
     * For example, by default '@yii' is registered as the alias to the Yii framework directory,
     * say '/path/to/yii'. The alias '@yii/web' would then be translated into '/path/to/yii/web'.
     *
     * If you have registered two aliases '@foo' and '@foo/bar'. Then translating '@foo/bar/config'
     * would replace the part '@foo/bar' (instead of '@foo') with the corresponding registered path.
     * This is because the longest alias takes precedence.
     *
     * However, if the alias to be translated is '@foo/barbar/config', then '@foo' will be replaced
     * instead of '@foo/bar', because '/' serves as the boundary character.
     *
     * Note, this method does not check if the returned path exists or not.
     *
     * See the [guide article on aliases](guide:concept-aliases) for more information.
     *
     * @param string $alias the alias to be translated.
     * @param bool $throwException whether to throw an exception if the given alias is invalid.
     * If this is false and an invalid alias is given, false will be returned by this method.
     * @return string|bool the path corresponding to the alias, false if the root alias is not previously registered.
     * @throws InvalidArgumentException if the alias is invalid while $throwException is true.
     * @see setAlias()
     */
    public function getAlias($alias, $throwException = true)
    {
        if (strncmp($alias, '@', 1)) {
            // not an alias
            return $alias;
        }

        $result = $this->findAlias($alias);

        if (is_array($result)) {
            return $result['path'];
        }

        if ($throwException) {
            throw new InvalidArgumentException("Invalid path alias: $alias");
        }

        return false;
    }

    /**
     * Returns the root alias part of a given alias.
     * A root alias is an alias that has been registered via [[setAlias()]] previously.
     * If a given alias matches multiple root aliases, the longest one will be returned.
     * @param string $alias the alias
     * @return string|bool the root alias, or false if no root alias is found
     */
    public function getRootAlias($alias)
    {
        $result = $this->findAlias($alias);
        if (is_array($result)) {
            $result = $result['root'];
        }
        return $result;
    }

    /**
     * @param string $alias
     * @return array|bool
     */
    protected function findAlias(string $alias)
    {
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (isset($this->aliases[$root])) {
            if (is_string($this->aliases[$root])) {
                return ['root' => $root, 'path' => $pos === false ? $this->aliases[$root] : $this->aliases[$root] . substr($alias, $pos)];
            }

            foreach ($this->aliases[$root] as $name => $path) {
                if (strpos($alias . '/', $name . '/') === 0) {
                    return ['root' => $name, 'path' => $path . substr($alias, strlen($name))];
                }
            }
        }

        return false;
    }

    /**
     * Registers a path alias.
     *
     * A path alias is a short name representing a long path (a file path, a URL, etc.)
     * For example, we use '@yii' as the alias of the path to the Yii framework directory.
     *
     * A path alias must start with the character '@' so that it can be easily differentiated
     * from non-alias paths.
     *
     * Note that this method does not check if the given path exists or not. All it does is
     * to associate the alias with the path.
     *
     * Any trailing '/' and '\' characters in the given path will be trimmed.
     *
     * See the [guide article on aliases](guide:concept-aliases) for more information.
     *
     * @param string $alias the alias name (e.g. "@yii"). It must start with a '@' character.
     * It may contain the forward slash '/' which serves as boundary character when performing
     * alias translation by [[getAlias()]].
     * @param string $path the path corresponding to the alias. If this is null, the alias will
     * be removed. Trailing '/' and '\' characters will be trimmed. This can be
     *
     * - a directory or a file path (e.g. `/tmp`, `/tmp/main.txt`)
     * - a URL (e.g. `http://www.yiiframework.com`)
     * - a path alias (e.g. `@yii/base`). In this case, the path alias will be converted into the
     *   actual path first by calling [[getAlias()]].
     *
     * @throws InvalidArgumentException if $path is an invalid alias.
     * @see getAlias()
     */
    public function setAlias($alias, $path)
    {
        if (strncmp($alias, '@', 1)) {
            $alias = '@' . $alias;
        }
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);
        if ($path !== null) {
            $path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : $this->getAlias($path);
            if (!isset($this->aliases[$root])) {
                if ($pos === false) {
                    $this->aliases[$root] = $path;
                } else {
                    $this->aliases[$root] = [$alias => $path];
                }
            } elseif (is_string($this->aliases[$root])) {
                if ($pos === false) {
                    $this->aliases[$root] = $path;
                } else {
                    $this->aliases[$root] = [
                        $alias => $path,
                        $root => $this->aliases[$root],
                    ];
                }
            } else {
                $this->aliases[$root][$alias] = $path;
                krsort($this->aliases[$root]);
            }
        } elseif (isset($this->aliases[$root])) {
            if (is_array($this->aliases[$root])) {
                unset($this->aliases[$root][$alias]);
            } elseif ($pos === false) {
                unset($this->aliases[$root]);
            }
        }
    }

    /**
     * Attempts to flush logger messages.
     * @since 3.0.0
     */
    protected function flushLogger()
    {
        if ($this->logger instanceof \yii\log\Logger) {
            $this->logger->flush(true);
        }
    }
}
