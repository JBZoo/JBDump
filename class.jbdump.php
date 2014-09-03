<?php
/**
 * Library for dump variables and profiling PHP code
 * The idea and the look was taken from Krumo project
 * PHP version 5.2 or higher
 *
 * Example:<br/>
 *      jbdump($myLoveVariable);<br/>
 *      jbdump($myLoveVariable, false, 'Var name');<br/>
 *      jbdump::mark('Profiler mark');<br/>
 *      jbdump::log('Message to log file');<br/>
 *      jbdump::i()->dump($myLoveVariable);<br/>
 *      jbdump::i()->post()->get()->mark('Profiler mark');<br/>
 *
 * Simple include in project on index.php file
 *      require_once './class.jbdump.php';
 *
 * @package     JBDump
 * @version     1.3.0
 * @copyright   Copyright (c) 2009-2012 Joomla-book.ru
 * @license     GNU General Public License version 2 or later
 * @author      Denis Smetannikov aka smet.denis <admin@joomla-book.ru>
 * @link        http://joomla-book.ru/projects/jbdump
 * @link        http://krumo.sourceforge.net/
 * @link        http://code.google.com/intl/ru-RU/apis/chart/index.html
 */

class JBDump
{
    /**
     * Default configurations
     * @var array
     */
    protected static $_config = array
    (
        'root'     => false, // project root directory
        'showArgs' => false, // show Args in backtrace
        'showCall' => true,

        // // // file logger
        'log'      => array(
            'path'      => false, // absolute log path
            'file'      => 'jbdump', // log filename
            'format'    => "{DATETIME}\t{CLIENT_IP}\t\t{FILE}\t\t{NAME}\t\t{JBDUMP_MESSAGE}", // fields in log file
            'serialize' => 'print_r', // (none|json|serialize|print_r|var_dump|format)
        ),

        // // // profiler
        'profiler' => array(
            'auto'      => true, // Result call automatically on destructor
            'render'    => 29, // Profiler render (bit mask). See constants jbdump::PROFILER_RENDER_*
            'showStart' => 0, // Set auto mark after jbdump init
            'showEnd'   => 0, // Set auto mark before jbdump destruction
        ),

        // // // sorting (ASC)
        'sort'     => array(
            'array'   => false, // by keys
            'object'  => true, // by properties name
            'methods' => true, // by methods name
        ),

        // // // personal dump
        'personal' => array(
            'ip'           => array(), // IP address for which to work debugging
            'requestParam' => false, // $_REQUEST key for which to work debugging
            'requestValue' => false, // $_REQUEST value for which to work debugging
        ),

        // // // error handlers
        'errors'   => array(
            'reporting'          => false, // set error reporting level while construct
            'errorHandler'       => true, // register own handler for PHP errors
            'errorBacktrace'     => false, // show backtrace for errors
            'exceptionHandler'   => true, // register own handler for all exeptions
            'exceptionBacktrace' => true, // show backtrace for exceptions
            'context'            => false, // show context for errors
            'logHidden'          => false, // if error message not show, log it
            'logAll'             => false, // log all error in syslog
        ),

        // // // mail send
        'mail'     => array(
            'to'      => 'jbdump@example.com', // mail to
            'subject' => 'JBDump debug', // mail subject
            'log'     => false, // log all mail messages
        ),

        // // // dump config
        'dump'     => array(
            'render'            => 'html', // (lite|log|mail|print_r|var_dump|html)
            'stringLength'      => 100, // cutting long string
            'maxDepth'          => 5, // the maximum depth of the dump
            'showMethods'       => true, // show object methods
            'die'               => false, // die after dump variable
            'stringExtra'       => true, // always show extra for strings
            'stringTextarea'    => false, // show string vars in <textarea> or in <pre>
            'expandLevel'       => 0, // expand the list to the specified depth
        ),
    );

    /**
     * Flag enable or disable the debugger
     * @var bool
     */
    public static $enabled = true;

    /**
     * Library version
     * @var string
     */
    const VERSION = '1.3.0';

    /**
     * Library version
     * @var string
     */
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Render type bit
     */
    const PROFILER_RENDER_NONE  = 0;
    const PROFILER_RENDER_FILE  = 1;
    const PROFILER_RENDER_ECHO  = 2;
    const PROFILER_RENDER_TABLE = 4;
    const PROFILER_RENDER_CHART = 8;
    const PROFILER_RENDER_TOTAL = 16;

    /**
     * Directory separator
     */
    const DS = '/';

    /**
     * Site url
     * @var string
     */
    protected $_site = 'http://joomla-book.ru/projects/jbdump';

    /**
     * Last backtrace
     * @var array
     */
    protected $_trace = array();

    /**
     * Absolute path current log file
     * @var string|resource
     */
    protected $_logfile = null;

    /**
     * Absolute path for all log files
     * @var string
     */
    protected $_logpath = null;

    /**
     * Current depth in current dumped object or array
     * @var integer
     */
    protected $_currentDepth = 0;

    /**
     * Profiler buffer info
     * @var array
     */
    protected $_bufferInfo = array();

    /**
     * Start microtime
     * @var float
     */
    protected $_start = 0.0;

    /**
     * Previous microtime for profiler
     * @var float
     */
    protected $_prevTime = 0.0;

    /**
     * Previous memory value for profiler
     * @var float
     */
    protected $_prevMemory = 0.0;

    /**
     * Fix bug anticycling destructor
     * @var bool
     */
    protected static $_isDie = false;

    /**
     * Constructor, set internal variables and self configuration
     * @param array  $options    OPTIONAL  Initialization parameters
     */
    protected function __construct(array $options = array())
    {
        $this->setParams($options);

        if (self::$_config['errors']['errorHandler']) {
            set_error_handler(array($this, '_errorHandler'));
        }

        if (self::$_config['errors']['exceptionHandler']) {
            set_exception_handler(array($this, '_exceptionHandler'));
        }

        $this->_start        = $this->_microtime();
        $this->_bufferInfo[] = array(
            'time'        => 0,
            'timeDiff'    => 0,
            'memory'      => self::_getMemory(),
            'memoryDiff'  => 0,
            'label'       => 'jbdump::init',
            'trace'       => '',
        );

        return $this;
    }

    /**
     * Destructor, call _shutdown method
     */
    function __destruct()
    {
        if (!self::$_isDie) {

            self::$_isDie = true;

            if (self::$_config['profiler']['showEnd']) {
                self::mark('jbdump::end');
            }

            $this->profiler(self::$_config['profiler']['render']);
        }
    }

    /**
     * Returns the global JBDump object, only creating it
     * if it doesn't already exist
     * @static
     * @param   array   $options    OPTIONAL  Initialization parameters
     * @return  JBDump
     */
    public static function i($options = array())
    {
        static $instance;

        if (!isset($instance)) {
            $instance = new self($options);

            if (self::$_config['profiler']['showStart']) {
                self::mark('jbdump::start');
            }

        }

        return $instance;
    }

    /**
     * Check permissions for show all debug messages
     *  - check ip, it if set in config
     *  - check requestParam, if it set in config
     *  - else return self::$enabled
     * @return  bool
     */
    public static function isDebug()
    {

        $result = self::$enabled;
        if ($result) {

            if (self::$_config['personal']['ip']) {

                if (is_array(self::$_config['personal']['ip'])) {
                    $result = in_array(self::getClientIP(), self::$_config['personal']['ip']);

                } else {
                    $result = self::getClientIP() == self::$_config['personal']['ip'];

                }

            }

            if (self::$_config['personal']['requestParam'] && $result) {

                if (isset($_REQUEST[self::$_config['personal']['requestParam']])
                        &&
                        $_REQUEST[self::$_config['personal']['requestParam']] == self::$_config['personal']['requestValue']
                ) {
                    $result = true;
                } else {
                    $result = false;
                }
            }

        }

        return $result;
    }

    /**
     * Force show PHP error messages
     * @static
     * @param $reportLevel OPTIONAL error_reporting
     * @return bool|JBDump
     */
    public static function showErrors($reportLevel = -1)
    {
        if (!self::isDebug()) {
            return false;
        }

        if ($reportLevel === null || $reportLevel === false) {
            return false;
        }

        if ($reportLevel != 0) {
            error_reporting($reportLevel);
            ini_set('error_reporting', $reportLevel);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);

        } else {
            error_reporting(0);
            ini_set('error_reporting', 0);
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
        }

        return true;
    }

    /**
     * Set max execution time
     * @param   integer $time  OPTIONAL  Time limit in seconds
     * @return  JBDump
     */
    public static function maxTime($time = 600)
    {
        if (!self::isDebug()) {
            return false;
        }

        ini_set('max_execution_time', $time);
        set_time_limit($time);

        return self::i();
    }

    /**
     * Enable debug
     * @return  JBDump
     */
    public static function on()
    {
        self::$enabled = true;
        return self::i();
    }

    /**
     * Disable debug
     * @return  JBDump
     */
    public static function off()
    {
        self::$enabled = false;
        return self::i();
    }

    /**
     * Set debug parameters
     * @param array  $data    Params for debug, see self::$_config vars
     * @param string $section
     * @return JBDump
     */
    public function setParams($data, $section = null)
    {
        if ($section) {
            $newData = array($section => $data);
            $data    = $newData;
            unset($newData);
        }

        if (isset($data['errors']['reporting'])) {
            $this->showErrors($data['errors']['reporting']);
        }

        // set root directory
        if (!isset($data['root']) && !self::$_config['root']) {
            $data['root'] = $_SERVER['DOCUMENT_ROOT'];
        }

        // set log path
        if (isset($data['log']['path']) && $data['log']['path']) {
            $this->_logpath = $data['log']['path'];

        } elseif (!self::$_config['log']['path'] || !$this->_logpath) {
            $this->_logpath = dirname(__FILE__) . self::DS . 'logs';
        }

        // set log filename
        $logFile = 'jbdump';
        if (isset($data['log']['file']) && $data['log']['file']) {
            $logFile = $data['log']['file'];

        } elseif (!self::$_config['log']['file'] || !$this->_logfile) {
            $logFile = 'jbdump';
        }

        $this->_logfile = $this->_logpath . self::DS . $logFile . '_' . date('Y.m.d') . '.log.php';

        // merge new params with of config
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $keyInner => $valueInner) {
                    if (!isset(self::$_config[$key])) {
                        self::$_config[$key] = array();
                    }
                    self::$_config[$key][$keyInner] = $valueInner;
                }
            } else {
                self::$_config[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Show client IP
     * @return  JBDump
     */
    public static function ip()
    {
        if (!self::isDebug()) {
            return false;
        }

        $ip = self::getClientIP();

        $data = array(
            'ip'        => $ip,
            'host'      => gethostbyaddr($ip),
            'source'    => '$_SERVER["' . self::getClientIP(true) . '"]',
            'inet_pton' => inet_pton($ip),
            'ip2long'   => ip2long($ip),
        );

        return self::i()->dump($data, '! my IP = ' . $ip . ' !');
    }

    /**
     * Show $_GET array
     * @return  JBDump
     */
    public static function get()
    {
        if (!self::isDebug()) {
            return false;
        }

        return self::i()->dump($_GET, '! $_GET !');
    }

    /**
     * Add message to log file
     * @param   mixed   $entry    Text to log file
     * @param   string  $markName OPTIONAL  Name of log record
     * @param   array   $params   OPTIONAL  Additional params
     * @return  JBDump
     */
    public static function log($entry, $markName = '...', $params = array())
    {
        if (!self::isDebug()) {
            return false;
        }

        // emulate normal class
        $_this = self::i();

        // check var type
        if (is_bool($entry)) {
            $entry = ($entry) ? 'TRUE' : 'FALSE';
        } elseif (is_null($entry)) {
            $entry = 'NULL';
        }

        // serialize type
        if (self::$_config['log']['serialize'] == 'format') {
            // don't change log entry

        } elseif (self::$_config['log']['serialize'] == 'none') {
            $entry = array('jbdump_message' => $entry);

        } elseif (self::$_config['log']['serialize'] == 'json') {
            $entry = array('jbdump_message' => @json_encode($entry));

        } elseif (self::$_config['log']['serialize'] == 'serialize') {
            $entry = array('jbdump_message' => serialize($entry));

        } elseif (self::$_config['log']['serialize'] == 'print_r') {
            $entry = array('jbdump_message' => print_r($entry, true));

        } elseif (self::$_config['log']['serialize'] == 'var_dump') {
            ob_start();
            var_dump($entry);
            $entry = ob_get_clean();
            $entry = array('jbdump_message' => var_dump($entry, true));
        }

        if (isset($params['trace'])) {
            $_this->_trace = $params['trace'];
        } else {
            $_this->_trace = debug_backtrace();
        }

        $entry['name']      = $markName;
        $entry['datetime']  = date(self::DATE_FORMAT);
        $entry['client_ip'] = self::getClientIP();
        $entry['file']      = $_this->_getSourcePath($_this->_trace, true);
        $entry              = array_change_key_case($entry, CASE_UPPER);

        $fields = array();
        $format = isset($params['format']) ? $params['format'] : self::$_config['log']['format'];
        preg_match_all("/{(.*?)}/i", $format, $fields);

        // Fill in the field data
        $line = $format;
        for ($i = 0; $i < count($fields[0]); $i++) {
            $line = str_replace($fields[0][$i], (isset ($entry[$fields[1][$i]])) ? $entry[$fields[1][$i]] : "-", $line);
        }

        // Write the log entry line
        if ($_this->_openLog()) {
            error_log($line . "\n", 3, $_this->_logfile);
        }

        return $_this;
    }

    /**
     * Open log file
     * @return  bool
     */
    function _openLog()
    {

        if (!@file_exists($this->_logfile)) {

            if (!is_dir($this->_logpath) && $this->_logpath) {
                mkdir($this->_logpath, 0777, true);
            }

            $header[] = "#<?php die('Direct Access To Log Files Not Permitted'); ?>";
            $header[] = "#Date: " . date(DATE_RFC822, time());
            $header[] = "#Software: JBDump v" . self::VERSION . ' by Joomla-book.ru';
            $fields   = str_replace("{", "", self::$_config['log']['format']);
            $fields   = str_replace("}", "", $fields);
            $fields   = strtolower($fields);
            $header[] = '#' . str_replace("\t", "\t", $fields);

            $head = implode("\n", $header);
        } else {
            $head = false;
        }

        if ($head) {
            error_log($head . "\n", 3, $this->_logfile);
        }

        return true;
    }

    /**
     * Show $_FILES array
     * @return  JBDump
     */
    public static function files()
    {
        if (!self::isDebug()) {
            return false;
        }

        return self::i()->dump($_FILES, '! $_FILES !');
    }

    /**
     * Show current usage memory in filesize format
     * @return  JBDump
     */
    public static function memory()
    {
        if (!self::isDebug()) {
            return false;
        }

        $memory = self::i()->_getMemory();
        $memory = self::i()->_formatSize($memory);
        return self::i()->dump($memory, '! memory !');
    }

    /**
     * Show declared interfaces
     * @return  JBDump
     */
    public static function interfaces()
    {
        if (!self::isDebug()) {
            return false;
        }

        return self::i()->dump(get_declared_interfaces(), '! interfaces !');
    }

    /**
     * Parse url
     * @param   string  $url      URL string
     * @param   string  $varname  OPTIONAL URL name
     * @return  JBDump
     */
    public static function url($url, $varname = '...')
    {
        if (!self::isDebug()) {
            return false;
        }

        $parsed = parse_url($url);

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $parsed['query_parsed']);
        }

        return self::i()->dump($parsed, $varname);
    }

    /**
     * Show included files
     * @return  JBDump
     */
    public static function includes()
    {
        if (!self::isDebug()) {
            return false;
        }

        return self::i()->dump(get_included_files(), '! includes files !');
    }

    /**
     * Show defined functions
     * @param   bool $showInternal OPTIONAL Get only internal functions
     * @return  JBDump
     */
    public static function functions($showInternal = false)
    {
        if (!self::isDebug()) {
            return false;
        }

        $functions = get_defined_functions();
        if ($showInternal) {
            $functions = $functions['internal'];
            $type      = 'internal';
        } else {
            $functions = $functions['user'];
            $type      = 'user';
        }

        return self::i()->dump($functions, '! functions (' . $type . ') !');
    }

    /**
     * Show defined constants
     * @static
     * @param bool $showAll Get only user defined functions
     * @return bool|JBDump
     */
    public static function defines($showAll = false)
    {
        if (!self::isDebug()) {
            return false;
        }

        $defines = get_defined_constants(true);
        if (!$showAll) {
            $defines = (isset($defines['user'])) ? $defines['user'] : array();
        }

        return self::i()->dump($defines, '! defines !');
    }

    /**
     * Show loaded PHP extensions
     * @param   bool $zend  Get only Zend extensions
     * @return  JBDump
     */
    public static function extensions($zend = false)
    {
        if (!self::isDebug()) {
            return false;
        }
        return self::i()->dump(get_loaded_extensions($zend), '! extensions ' . ($zend ? '(Zend)' : '') . ' !');
    }

    /**
     * Show HTTP headers
     * @return  JBDump
     */
    public static function headers()
    {
        if (!self::isDebug()) {
            return false;
        }

        if (function_exists('apache_request_headers')) {
            $data = array(
                'Request'  => apache_request_headers(),
                'Response' => apache_response_headers(),
                'List'     => headers_list()
            );

        } else {
            $data = array(
                'List' => headers_list()
            );
        }

        if (headers_sent($filename, $linenum)) {
            $data['Sent'] = 'Headers already sent in ' . self::i()->_getRalativePath($filename) . ':' . $linenum;
        } else {
            $data['Sent'] = false;
        }

        return self::i()->dump($data, '! headers !');
    }

    /**
     * Show php.ini content (open php.ini file)
     * @return  JBDump
     */
    public static function phpini()
    {
        if (!self::isDebug()) {
            return false;
        }

        $data = get_cfg_var('cfg_file_path');
        if (!@file($data)) {
            return false;
        }
        $ini = parse_ini_file($data, true);
        return self::i()->dump($ini, '! php.ini !');
    }

    /**
     * Show php.ini content (PHP API)
     * @param   string  $extension  Extension name
     * @param   bool    $details    Retrieve details settings or only the current value for each setting
     * @return  bool|JBDump
     */
    public static function conf($extension = '', $details = true)
    {
        if (!self::isDebug()) {
            return false;
        }

        if ($extension == '') {
            $label = '';
            $data  = ini_get_all();
        } else {
            $label = ' (' . $extension . ') ';
            $data  = ini_get_all($extension, $details);
        }

        return self::i()->dump($data, '! configuration settings' . $label . ' !');
    }

    /**
     * Show included and system paths
     * @return  JBDump
     */
    public static function path()
    {
        if (!self::isDebug()) {
            return false;
        }

        $result = array(
            'get_include_path' => explode(PATH_SEPARATOR, trim(get_include_path(), PATH_SEPARATOR)),
            '$_SERVER[PATH]'   => explode(PATH_SEPARATOR, trim($_SERVER['PATH'], PATH_SEPARATOR))
        );

        return self::i()->dump($result, '! paths !');
    }

    /**
     * Show $_REQUEST array or dump $_GET, $_POST, $_COOKIE
     * @static
     * @param bool $notReal Get real $_REQUEST array
     * @return bool|JBDump
     */
    public static function request($notReal = false)
    {
        if (!self::isDebug()) {
            return false;
        }

        if ($notReal) {
            self::get();
            self::post();
            self::cookie();
            return self::files();
        } else {
            return self::i()->dump($_REQUEST, '! $_REQUEST !');
        }
    }

    /**
     * Show $_POST array
     * @return  JBDump
     */
    public static function post()
    {
        if (!self::isDebug()) {
            return false;
        }
        return self::i()->dump($_POST, '! $_POST !');
    }

    /**
     * Show $_SERVER array
     * @return  JBDump
     */
    public static function server()
    {
        if (!self::isDebug()) {
            return false;
        }
        return self::i()->dump($_SERVER, '! $_SERVER !');
    }

    /**
     * Show $_COOKIE array
     * @return  JBDump
     */
    public static function cookie()
    {
        if (!self::isDebug()) {
            return false;
        }
        return self::i()->dump($_COOKIE, '! $_COOKIE !');
    }

    /**
     * Show parsed JSON data
     * @static
     * @param        $jsonData  JSON data
     * @param string $name      OPTIONAL Variable name
     * @return bool|JBDump
     */
    public static function json($json, $name = '...')
    {
        if (!self::isDebug()) {
            return false;
        }

        $jsonData = json_decode($json);        
        $result   = self::i()->_jsonEncode($jsonData);
        
        return self::i()->dump($result, $name);
    }
    
    /**
     * Convert JSON format to human readability
     * @static
     * @param string $json
     * @param string $varName
     * @return JBDump
     */
    public static function jsonFormat($json, $varName = '...')
    {


        return $result;
    }    

    /**
     * Show $_ENV array
     * @return  JBDump
     */
    public static function env()
    {
        if (!self::isDebug()) {
            return false;
        }

        return self::i()->dump($_ENV, '! $_ENV !');
    }

    /**
     * Show $_SESSION array
     * @return  JBDump
     */
    public static function session()
    {
        $sessionId = session_id();
        if (!$sessionId) {
            $_SESSION  = 'PHP session don\'t start';
            $sessionId = '';
        } else {
            $sessionId = ' (' . $sessionId . ') ';
        }

        return self::i()->dump($_SESSION, '! $_SESSION ' . $sessionId . ' !');
    }

    /**
     * Show $GLOBALS array
     * @return  JBDump
     */
    public static function globals()
    {
        if (!self::isDebug()) {
            return false;
        }

        return self::i()->dump($GLOBALS, '! $GLOBALS !');
    }

    /**
     * Convert timestamp to normal date, in DATE_RFC822 format
     * @static
     * @param   null|integer $timestamp Time in Unix timestamp format
     * @return  bool|JBDump
     */
    public static function timestamp($timestamp = null)
    {
        if (!self::isDebug()) {
            return false;
        }

        $date = date(DATE_RFC822, $timestamp);
        return self::i()->dump($date, $timestamp . ' sec = ');
    }

    /**
     * Find all locale in system
     * list - only for linux like systems
     * @return  JBDump
     */
    public static function locale()
    {
        if (!self::isDebug()) {
            return false;
        }

        ob_start();
        @system('locale -a');
        $locale = explode("\n", trim(ob_get_contents()));
        ob_end_clean();

        $result = array(
            'list' => $locale,
            'conv' => @localeconv()
        );

        return self::i()->dump($result, '! locale info !');
    }

    /**
     * Show date default timezone
     * @return  JBDump
     */
    public static function timezone()
    {
        if (!self::isDebug()) {
            return false;
        }

        $data = date_default_timezone_get();
        return self::i()->dump($data, '! timezone !');
    }

    /**
     * Wrapper for PHP print_r function
     * @static
     * @param mixed  $var     The variable to dump
     * @param string $varname OPTIONAL Label to prepend to output
     * @param array  $params  OPTIONAL Echo output if true
     * @return bool|JBDump
     */
    public static function print_r($var, $varname = '...', $params = array())
    {
        if (!self::isDebug()) {
            return false;
        }

        $output = print_r($var, true);

        $_this = self::i();
        $_this->_dumpRenderHtml($output, $varname, $params);

        return $_this;
    }

    /**
     * Wrapper for PHP var_dump function
     * @static
     * @param   mixed   $var     The variable to dump
     * @param   string  $varname OPTIONAL Echo output if true
     * @param   array   $params  OPTIONAL Additionls params
     * @return bool|JBDump
     */
    public static function var_dump($var, $varname = '...', $params = array())
    {
        if (!self::isDebug()) {
            return false;
        }

        // var_dump the variable into a buffer and keep the output
        ob_start();
        var_dump($var);
        $output = ob_get_clean();

        // neaten the newlines and indents
        $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
        if (!extension_loaded('xdebug')) {
            $output = htmlspecialchars($output, ENT_QUOTES);
        }

        $_this = self::i();
        $_this->_dumpRenderHtml($output, $varname . '::html', $params);

        return $_this;
    }

    /**
     * Get system backtrace in formated view
     * @param   bool $trace      Custom php backtrace
     * @param   bool $addObject  Show objects in result
     * @return  JBDump
     */
    public static function trace($trace = null, $addObject = false)
    {
        if (!self::isDebug()) {
            return false;
        }

        $_this = self::i();

        $trace = $trace ? $trace : debug_backtrace($addObject);
        unset($trace[0]);

        $result = $_this->convertTrace($trace, $addObject);

        return $_this->dump($result, '! backtrace !');
    }

    /**
     * Show declared classes
     * @static
     * @param bool $sort
     * @return bool|JBDump
     */
    public static function classes($sort = false)
    {
        if (!self::isDebug()) {
            return false;
        }

        $classes = get_declared_classes();
        if ((bool)$sort) {
            sort($classes);
        }

        return self::i()->dump($classes, '! classes !');
    }

    /**
     * Show declared classes
     * @static
     * @param $object
     * @return bool|JBDump
     */
    public static function methods($object)
    {
        if (!self::isDebug()) {
            return false;
        }

        $methodst = self::i()->_getMethods($object);
        if (is_string($object)) {
            $className = $object;
        } else {
            $className = get_class($object);
        }

        return self::i()->dump($methodst, '&lt;! methods of "' . $className . '" !&gt;');
    }

    /**
     * Dump info about class (object)
     * @param   string|object  $data    Object or class name
     * @return  JBDump
     */
    public static function classInfo($data)
    {
        $result = self::_getClass($data);
        if ($result) {
            $data = $result['name'];
        }

        return self::i()->dump($result, '! class (' . $data . ') !');
    }

    /**
     * Dump all info about extension
     * @static
     * @param   string  $extensionName  Extension name
     * @return  JBDump
     */
    public static function extInfo($extensionName)
    {

        $result = self::_getExtension($extensionName);
        if ($result) {
            $extensionName = $result['name'];
        }

        return self::i()->dump($result, '! extension (' . $extensionName . ') !');
    }

    /**
     * Dump all file info
     * @param   string  $file   path to file
     * @return  JBDump
     */
    public static function pathInfo($file)
    {
        $result = self::_pathInfo($file);
        return self::i()->dump($result, '! pathInfo (' . $file . ') !');
    }

    /**
     * Dump all info about function
     * @static
     * @param   string|Closure $functionName    Closure or function name
     * @return  JBDump
     */
    public static function funcInfo($functionName)
    {
        $result = self::_getFunction($functionName);
        if ($result) {
            $functionName = $result['name'];
        }
        return self::i()->dump($result, '! function (' . $functionName . ') !');
    }

    /**
     * Show current microtime
     * @return  JBDump
     */
    public static function microtime()
    {
        $_this = self::i();
        if (!$_this->isDebug()) {
            return false;
        }

        $data = $_this->_microtime();

        return $_this->dump($data, '! current microtime !');
    }

    /**
     * Output a time mark
     * The mark is returned as text current profiler status
     * @param   string  $label OPTIONAL A label for the time mark
     * @return  JBDump
     */
    public static function mark($label = '')
    {
        $_this = self::i();
        if (!$_this->isDebug()) {
            return false;
        }

        $current = $_this->_microtime() - $_this->_start;
        $memory  = self::_getMemory();
        $trace   = debug_backtrace();

        $markInfo = array(
            'time'       => $current,
            'timeDiff'   => $current - $_this->_prevTime,
            'memory'     => $memory,
            'memoryDiff' => $memory - $_this->_prevMemory,
            'trace'      => $_this->_getSourcePath($trace, true),
            'label'      => $label,
        );

        $_this->_bufferInfo[] = $markInfo;

        if ((int)self::$_config['profiler']['render'] & self::PROFILER_RENDER_FILE) {
            $_this->log(self::_profilerFormatMark($markInfo), 'mark #');
        }

        $_this->_prevTime   = $current;
        $_this->_prevMemory = $memory;

        return $_this;
    }

    /**
     * Show profiler result
     * @param   int    $mode Render mode
     * @return  JBDump
     */
    public function profiler($mode = 1)
    {
        if ($this->isDebug() && count($this->_bufferInfo) > 2 && $mode) {

            $mode = (int)$mode;

            if ($mode && self::isAjax()) {
                if ($mode & self::PROFILER_RENDER_TOTAL) {
                    $this->_profilerRenderTotal();
                }

            } else {
                if ($mode & self::PROFILER_RENDER_TABLE) {
                    $this->_profilerRenderTable();
                }

                if ($mode & self::PROFILER_RENDER_CHART) {
                    $this->_profilerRenderChart();
                }

                if ($mode & self::PROFILER_RENDER_TOTAL) {
                    $this->_profilerRenderTotal();
                }

                if ($mode & self::PROFILER_RENDER_ECHO) {
                    $this->_profilerRenderEcho();
                }
            }
        }
    }

    /**
     * Convert profiler memory value to usability view
     * @static
     * @param int $memoryBits
     * @return float
     */
    protected static function _profilerFormatMemory($memoryBits)
    {
        return round($memoryBits / 1024 / 1024, 3);
    }

    /**
     * Convert profiler time value to usability view
     * @static
     * @param $time
     * @return float
     */
    protected static function _profilerFormatTime($time)
    {
        return round($time * 1000, 0);
    }

    /**
     * Convert profiler mark to string
     * @static
     * @param array $mark
     * @return string
     */
    protected static function _profilerFormatMark(array $mark)
    {
        return sprintf("%0.3f sec (+%.3f); %0.3f MB (%s%0.3f) - %s",
            (float)$mark['time'],
            (float)$mark['timeDiff'],
            ($mark['memory'] / 1024 / 1024),
            ($mark['memoryDiff'] / 1024 / 1024 >= 0) ? '+' : '',
            ($mark['memoryDiff'] / 1024 / 1024),
            $mark['label']
        );
    }

    /**
     * Profiler render - total info
     */
    protected function _profilerRenderTotal()
    {
        reset($this->_bufferInfo);
        $first      = current($this->_bufferInfo);
        $last       = end($this->_bufferInfo);
        $memoryPeak = memory_get_peak_usage(true);

        $memoryDeltas = $timeDeltas = array();
        foreach ($this->_bufferInfo as $oneMark) {
            $memoryDeltas[] = $oneMark['memoryDiff'];
            $timeDeltas[]   = $oneMark['timeDiff'];
        }

        $totalInfo   = array();
        $totalInfo[] = '- Points: ' . count($this->_bufferInfo);
        $totalInfo[] = '-------- Time (ms)';
        $totalInfo[] = '- Max delta, msec: ' . self::_profilerFormatTime(max($timeDeltas));
        $totalInfo[] = '- Min delta, msec: ' . self::_profilerFormatTime(min($timeDeltas));
        $totalInfo[] = '- Total delta, msec: ' . self::_profilerFormatTime(($last['time'] - $first['time']));
        $totalInfo[] = '- Limit, sec: ' . ini_get('max_execution_time');
        $totalInfo[] = '-------- Memory (MB)';
        $totalInfo[] = '- Max delta: ' . self::_profilerFormatMemory(max($memoryDeltas));
        $totalInfo[] = '- Min delta: ' . self::_profilerFormatMemory(min($memoryDeltas));
        $totalInfo[] = '- Usage on peak: ' . $this->_formatSize($memoryPeak) . ' (' . $memoryPeak . ')';
        $totalInfo[] = '- Total delta: ' . self::_profilerFormatMemory($last['memory'] - $first['memory']);
        $totalInfo[] = '- Limit: ' . ini_get('memory_limit');

        if (self::isAjax()) {
            $this->_dumpRenderLog($totalInfo, 'Profiler total');
        } else {
            $totalInfo = "\n\t" . implode("\n\t", $totalInfo) . "\n";
            $this->_dumpRenderLite($totalInfo, '! <b>profiler total info</b> !');
        }
    }

    /**
     * Profile render - to log file
     */
    protected function _profilerRenderFile()
    {
        $this->log('-------------------------------------------------------', 'Profiler start');
        foreach ($this->_bufferInfo as $key => $mark) {
            $this->log(self::_profilerFormatMark($mark));
        }
        $this->log('-------------------------------------------------------', 'Profiler end');
    }

    /**
     * Profile render - echo lite
     */
    protected function _profilerRenderEcho()
    {
        $output = "\n";
        foreach ($this->_bufferInfo as $key => $mark) {
            $output .= "\t" . self::_profilerFormatMark($mark) . "\n";
        }
        $this->_dumpRenderLite($output, '! profiler !');
    }

    /**
     * Profiler render - table
     */
    protected function _profilerRenderTable()
    {
        $this->_initAssets();
        ?>
    <div id="jbdump_profile_chart_table" style="max-width: 1000px;margin:0 auto;text-align:left;"></div>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load('visualization', '1', {packages:['table']});
        google.setOnLoadCallback(function () {

            var data = new google.visualization.DataTable();

            data.addColumn('number', '#');
            data.addColumn('string', 'label');
            data.addColumn('string', 'file');
            data.addColumn('number', 'time, ms');
            data.addColumn('number', 'time delta, ms');
            data.addColumn('number', 'memory, MB');
            data.addColumn('number', 'memory delta, MB');

            data.addRows(<?php echo count($this->_bufferInfo);?>);

            <?php
            $i = 0;
            foreach ($this->_bufferInfo as $key=> $mark) {
                ?>
                data.setCell(<?php echo $key;?>, 0, <?php echo ++$i;?>);
                data.setCell(<?php echo $key;?>, 1, '<?php echo $mark['label'];?>');
                data.setCell(<?php echo $key;?>, 2, '<?php echo $mark['trace'];?>');
                data.setCell(<?php echo $key;?>, 3, <?php echo self::_profilerFormatTime($mark['time']);?>);
                data.setCell(<?php echo $key;?>, 4, <?php echo self::_profilerFormatTime($mark['timeDiff']);?>);
                data.setCell(<?php echo $key;?>, 5, <?php echo self::_profilerFormatMemory($mark['memory']);?>);
                data.setCell(<?php echo $key;?>, 6, <?php echo self::_profilerFormatMemory($mark['memoryDiff']);?>);
                <?php
            } ?>

            var formatter = new google.visualization.TableBarFormat({width:120});
            formatter.format(data, 4);
            formatter.format(data, 6);

            var table = new google.visualization.Table(document.getElementById('jbdump_profile_chart_table'));
            table.draw(data, {
                allowHtml    :true,
                showRowNumber:false
            });
        });
    </script>
    <?php
    }

    /**
     * Profiler render - table
     */
    protected function _profilerRenderChart()
    {
        ?>
    <div id="jbdump_profilter_chart_time" style="max-width: 1000px;margin:0 auto;text-align:left;"></div>
    <div id="jbdump_profilter_chart_memory" style="max-width: 1000px;margin:0 auto;text-align:left;"></div>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load("visualization", "1", {packages:["corechart"]});
        google.setOnLoadCallback(function drawChart() {
            //////////////////////////// time ////////////////////////////
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Label');
            data.addColumn('number', 'time, ms');
            data.addColumn('number', 'time delta, ms');
            data.addRows([
                <?php
                foreach ($this->_bufferInfo as $mark) {
                    echo '[\'' . $mark['label'] . '\', '
                            . self::_profilerFormatTime($mark['time']) . ', '
                            . self::_profilerFormatTime($mark['timeDiff']) . '],';
                } ?>
            ]);

            var chart = new google.visualization.LineChart(document.getElementById('jbdump_profilter_chart_time'));
            chart.draw(data, {
                'width' :750,
                'height':400,
                'title' :'JBDump profiler by time'
            });

            //////////////////////////// memory ////////////////////////////
            var data = new google.visualization.DataTable();

            data.addColumn('string', 'Label');
            data.addColumn('number', 'memory, MB');
            data.addColumn('number', 'memory delta, MB');
            data.addRows([
                <?php
                foreach ($this->_bufferInfo as $mark) {
                    echo '[\'' . $mark['label'] . '\', '
                            . self::_profilerFormatMemory($mark['memory']) . ', '
                            . self::_profilerFormatMemory($mark['memoryDiff']) . '],';
                } ?>
            ]);

            var chart = new google.visualization.LineChart(document.getElementById('jbdump_profilter_chart_memory'));
            chart.draw(data, {
                'width' :750,
                'height':400,
                'title' :'JBDump profiler by memory'
            });
        });
    </script>
    <?php
    }

    /**
     * Dumper variable
     * @param   mixed   $data     Mixed data for dump
     * @param   string  $varname  OPTIONAL Variable name
     * @param   array   $params   OPTIONAL Additional params
     * @return  JBDump
     */
    public static function dump($data, $varname = '...', $params = array())
    {
        if (!self::isDebug()) {
            return false;
        }

        $_this = self::i();

        if (self::isAjax()) {
            $_this->_dumpRenderLite($data, $varname, $params);

        } elseif (self::$_config['dump']['render'] == 'lite') {
            $_this->_dumpRenderLite($data, $varname, $params);

        } elseif (self::$_config['dump']['render'] == 'html') {
            $_this->_dumpRenderHtml($data, $varname, $params);

        } elseif (self::$_config['dump']['render'] == 'log') {
            $_this->_dumpRenderLog($data, $varname, $params);

        } elseif (self::$_config['dump']['render'] == 'mail') {
            $_this->_dumpRenderMail($data, $varname, $params);

        } elseif (self::$_config['dump']['render'] == 'print_r') {
            $_this->_dumpRenderPrintr($data, $varname, $params);

        } elseif (self::$_config['dump']['render'] == 'var_dump') {
            $_this->_dumpRenderVardump($data, $varname, $params);
        }

        if (self::$_config['dump']['die']) {
            die('JBDump_die');
        }

        return $_this;
    }

    /**
     * Dump render - HTML
     * @param mixed  $data
     * @param string $varname
     * @param array  $params
     */
    protected function _dumpRenderHtml($data, $varname = '...', $params = array())
    {
        $this->_currentDepth = 0;
        $this->_initAssets();

        if (isset($params['trace'])) {
            $this->_trace = $params['trace'];
        } else {
            $this->_trace = debug_backtrace();
        }

        $text = $this->_getSourceFunction($this->_trace);
        $path = $this->_getSourcePath($this->_trace);
        ?>
    <div class="krumo-root">
        <ul class="krumo-node krumo-first">
            <?php $this->_dump($data, $varname);?>
            <li class="krumo-footnote">

                <div class="copyrights">
                    <a href="<?php echo $this->_site;?>" target="_blank">JBDump v<?php echo self::VERSION;?></a>
                </div>

                <?php if (self::$_config['showCall']) :?>
                    <span class="krumo-call"><?php echo $text . ' ' . $path; ?></span>
                <?php endif;?>
            </li>
        </ul>
    </div>
    <?php
    }

    /**
     * Dump render - Lite mode
     * @param mixed  $data
     * @param string $varname
     * @param array  $params
     */
    protected function _dumpRenderLite($data, $varname = '...', $params = array())
    {
        if (is_bool($data)) {
            $data = $data ? 'TRUE' : 'FALSE';
        } elseif (is_null($data)) {
            $data = 'NULL';
        }

        $printrOut = print_r($data, true);
        $printrOut = htmlspecialchars($printrOut);

        if (self::isAjax()) {
            $printrOut = str_replace('] =&gt;', '] =>', $printrOut);
        }

        $output   = array();
        $output[] = "<pre>------------------------------\n";
        $output[] = $varname . ' = ';
        $output[] = rtrim($printrOut, "\n");
        $output[] = "\n------------------------------</pre>\n";
        if (!self::isAjax()) {
            echo '<pre class="krumo" style="text-align: left;">' . implode('', $output) . "</pre>\n";
        } else {
            echo implode('', $output);
        }
    }

    /**
     * Dump render - to logfile
     * @param mixed  $data
     * @param string $varname
     * @param array  $params
     */
    protected function _dumpRenderLog($data, $varname = '...', $params = array())
    {
        $this->log($data, $varname, $params);
    }

    /**
     * Dump render - send to email
     * @param mixed  $data
     * @param string $varname
     * @param array  $params
     */
    protected function _dumpRenderMail($data, $varname = '...', $params = array())
    {
        $this->mail(array('varname' => $varname,'data' => $data));
    }

    /**
     * Dump render - php print_r
     * @param mixed  $data
     * @param string $varname
     * @param array  $params
     */
    protected function _dumpRenderPrintr($data, $varname = '...', $params = array())
    {
        $this->print_r($data, $varname, $params);
    }

    /**
     * Dump render - php var_dump
     * @param mixed  $data
     * @param string $varname
     * @param array  $params
     */
    protected function _dumpRenderVardump($data, $varname = '...', $params = array())
    {
        $this->var_dump($data, $varname, $params);
    }

    /**
     * Get all available hash from data
     * @param   string  $data   Data from get hash
     * @return  JBDump
     */
    public static function hash($data)
    {
        $result = array();
        foreach (hash_algos() as $algoritm) {
            $result[$algoritm] = hash($algoritm, $data, false);
        }
        return self::i()->dump($result, '! hash !');
    }

    /**
     * Get current usage memory
     * @static
     * @return int
     */
    protected static function _getMemory()
    {
        if (function_exists('memory_get_usage')) {
            return memory_get_usage();
        } else {
            $output = array();
            $pid    = getmypid();

            if (substr(PHP_OS, 0, 3) == 'WIN') {
                @exec('tasklist /FI "PID eq ' . $pid . '" /FO LIST', $output);
                if (!isset($output[5])) {
                    $output[5] = null;
                }
                return (int)substr($output[5], strpos($output[5], ':') + 1);
            } else {
                @exec("ps -o rss -p $pid", $output);
                return $output[1] * 1024;
            }
        }
    }

    /**
     * Get current microtime
     * @static
     * @return float
     */
    public static function _microtime()
    {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * Check is current level is expanded
     */
    protected function _isExpandedLevel()
    {
        return $this->_currentDepth <= self::$_config['dump']['expandLevel'];
    }

    /**
     * Maps type variable to a function
     * @param   mixed   $data  Mixed data for dump
     * @param   string  $name  OPTIONAL Variable name
     * @return  JBDump
     */
    protected function _dump($data, $name = '...')
    {
        $varType = strtolower(getType($data));

        $advType = false;
        if ($varType == 'string' && preg_match('#(.*)::(.*)#', $name, $matches)) {
            $matches[2] = trim(strToLower($matches[2]));
            if (strlen($matches[2]) > 0) {
                $advType = $matches[2];
            }
            $name = $matches[1];
        }

        if ($varType == 'null') {
            $this->_null($name);

        } elseif ($varType == 'boolean') {
            $this->_boolean($data, $name, $advType);

        } elseif ($varType == 'integer') {
            $this->_integer($data, $name, $advType);

        } elseif ($varType == 'double') {
            $this->_float($data, $name, $advType);

        } elseif ($varType == 'string') {
            $this->_string($data, $name, $advType);

        } elseif ($varType == 'array') {
            if ($this->_currentDepth <= self::$_config['dump']['maxDepth']) {
                $this->_currentDepth++;
                $this->_array($data, $name, $advType);
                $this->_currentDepth--;
            } else {
                $this->_maxDepth($data, $name, $advType);
            }

        } elseif ($varType == 'object') {
            if ($this->_currentDepth <= self::$_config['dump']['maxDepth']) {
                $this->_currentDepth++;

                if (get_class($data) == 'Closure') {
                    $this->_closure($data, $name, $advType);
                } else {
                    $this->_object($data, $name, $advType);
                }

                $this->_currentDepth--;
            } else {
                $this->_maxDepth($data, $name);
            }

        } elseif ($varType == 'resource') {
            $this->_resource($data, $name, $advType);

        } else {
            $this->_undefined($data, $name, $advType);
        }

        return $this;
    }

    /**
     * Render HTML for object and array
     * @param   array|object $data       Variablevalue
     * @param   bool         $isExpanded Flag is current block expanded
     * @return  void
     */
    protected function _vars($data, $isExpanded = false)
    {
        $_is_object = is_object($data);

        ?>
    <div class="krumo-nest" style="<?php echo $isExpanded ? 'display:block' : 'display:none';?>">
        <ul class="krumo-node">
            <?php
            $keys = ($_is_object) ? array_keys(get_object_vars($data)) : array_keys($data);

            // sorting
            if (self::$_config['sort']['object'] && $_is_object) {
                sort($keys);
            } elseif (self::$_config['sort']['array']) {
                sort($keys);
            }

            // get entries
            foreach ($keys as $key) {
                $value = null;
                if ($_is_object) {
                    $value = $data->$key;
                } else {
                    if (array_key_exists($key, $data)) {
                        $value = $data[$key];
                    }
                }

                $this->_dump($value, $key);
            }

            // get methods
            if ($_is_object && self::$_config['dump']['showMethods']) {
                $methods = $this->_getMethods($data);
                $this->_dump($methods, '&lt;! methods of "' . get_class($data) . '" !&gt;');
            }
            ?>
        </ul>
    </div>
    <?php
    }

    /**
     * Render HTML for NULL type
     * @param   string  $name  Variable name
     * @return  void
     */
    protected function _null($name)
    {
        ?>
    <li class="krumo-child">
        <div class="krumo-element" onMouseOver="krumo.over(this);" onMouseOut="krumo.out(this);">
            <a class="krumo-name"><?php echo $name;?></a> (<em class="krumo-type krumo-null">NULL</em>)
        </div>
    </li>
    <?php
    }

    /**
     * Render HTML for Boolean type
     * @param   bool    $data  Variable
     * @param   string  $name  Variable name
     * @return  void
     */
    protected function _boolean($data, $name)
    {
        $data = $data ? 'TRUE' : 'FALSE';
        $this->_renderNode('Boolean', $name, $data);
    }

    /**
     * Render HTML for Integer type
     * @param   integer $data   Variable
     * @param   string  $name   Variable name
     * @return  void
     */
    protected function _integer($data, $name)
    {
        $this->_renderNode('Integer', $name, (int)$data);
    }

    /**
     * Render HTML for float (double) type
     * @param   float   $data   Variable
     * @param   string  $name   Variable name
     * @return  void
     */
    protected function _float($data, $name)
    {
        $this->_renderNode('Float', $name, (float)$data);
    }

    /**
     * Render HTML for resource type
     * @param   resource $data   Variable
     * @param   string   $name   Variable name
     * @return  void
     */
    protected function _resource($data, $name)
    {
        $data = get_resource_type($data);
        $this->_renderNode('Resource', $name, $data);
    }

    /**
     * Render HTML for string type
     * @param   string $data    Variable
     * @param   string $name    Variable name
     * @param   string $advType String type (parse mode)
     * @return  void
     */
    protected function _string($data, $name, $advType = '')
    {
        $dataLength = strlen($data);

        $_extra = (self::$_config['dump']['stringExtra'] && $data);
        if ($advType == 'html') {
            $_extra = true;
            $_      = 'HTML Code';

            $data = '<pre class="krumo">' . $data . '</pre>';

        } elseif ($advType == 'source') {
            $_extra = true;
            $_      = 'PHP Code';

            $data = trim($data);
            if (strpos($data, '<?') !== 0) {
                $data = "<?php\n" . $data;
            }

            $data = highlight_string($data, true);

        } else {
            $_ = $data;

            if (strlen($data)) {
                if (strLen($data) > self::$_config['dump']['stringLength']) {
                    if (function_exists('mb_substr')) {
                        $_ = mb_substr($data, 0, self::$_config['dump']['stringLength'] - 3) . '...';
                    } else {
                        $_ = substr($data, 0, self::$_config['dump']['stringLength'] - 3) . '...';
                    }
                    $_extra = true;
                }
                $_ = htmlSpecialChars($_);

                if (self::$_config['dump']['stringTextarea']) {
                    $data = '<textarea readonly="readonly" class="krumo">' . htmlSpecialChars($data) . '</textarea>';
                } else {
                    $data = '<pre class="krumo">' . htmlSpecialChars($data) . '</pre>';
                }
            }

        }
        ?>
    <li class="krumo-child">
        <div class="krumo-element <?php echo $_extra ? ' krumo-expand' : '';?>"
            <?php if ($_extra) { ?> onClick="krumo.toggle(this);"<?php }?> onMouseOver="krumo.over(this);"
             onMouseOut="krumo.out(this);">
            <a class="krumo-name"><?php echo $name;?></a>
            (<em class="krumo-type">String, <strong class="krumo-string-length"><?php echo $dataLength; ?></strong></em>)
            <strong class="krumo-string"><?php echo $_;?></strong>
        </div>
        <?php if ($_extra) { ?>
        <div class="krumo-nest" style="display:none;">
            <ul class="krumo-node">
                <li class="krumo-child">
                    <div class="krumo-preview"><?php echo $data;?></div>
                </li>
            </ul>
        </div>
        <?php } ?>
    </li>
    <?php

    }

    /**
     * Render HTML for array type
     * @param   array   $data  Variable
     * @param   string  $name  Variable name
     * @return  void
     */
    protected function _array(array $data, $name)
    {
        $isExpanded = $this->_isExpandedLevel();

        ?>
    <li class="krumo-child">
        <div class="krumo-element<?php echo count($data) > 0 ? ' krumo-expand' : '';?> <?=$isExpanded ? 'krumo-opened' : '';?>"
            <?php if (count($data) > 0) { ?> onClick="krumo.toggle(this);"<?php }?> onMouseOver="krumo.over(this);"
             onMouseOut="krumo.out(this);">
            <a class="krumo-name"><?php echo $name;?></a>
            (<em class="krumo-type">Array, <strong class="krumo-array-length"><?php echo count($data);?></strong></em>)
        </div>
        <?php if (count($data)) {
        $this->_vars($data, $isExpanded);
    } ?>
    </li>
    <?php
    }

    /**
     * Render HTML for object type
     * @param   object  $data  Variable
     * @param   string  $name  Variable name
     * @return  void
     */
    protected function _object($data, $name)
    {
        $isExpand   = count(get_object_vars($data)) > 0 || self::$_config['dump']['showMethods'];
        $isExpanded = $this->_isExpandedLevel();

        ?>
    <li class="krumo-child">
        <div class="krumo-element<?php echo $isExpand ? ' krumo-expand' : '';?> <?=$isExpanded ? 'krumo-opened' : '';?>"
            <?php if ($isExpand) { ?> onClick="krumo.toggle(this);"<?php }?> onMouseOver="krumo.over(this);"
             onMouseOut="krumo.out(this);">
            <a class="krumo-name"><?php echo $name;?></a>
            (<em class="krumo-type">Object, <?php echo count(get_object_vars($data)); ?></em>)
            <strong class="krumo-class"><?php echo get_class($data);?></strong>
        </div>
        <?php if ($isExpand) {
        $this->_vars($data, $isExpanded);
    } ?>
    </li>
    <?php
    }

    /**
     * Render HTML for closure type
     * @param   object  $data  Variable
     * @param   string  $name  Variable name
     * @return  void
     */
    protected function _closure($data, $name)
    {
        $isExpanded = $this->_isExpandedLevel();

        ?>
    <li class="krumo-child">
        <div class="krumo-element<?php echo count($data) > 0 ? ' krumo-expand' : '';?> <?=$isExpanded ? 'krumo-opened' : '';?>"
            <?php if (count($data) > 0) { ?> onClick="krumo.toggle(this);"<?php }?>
             onMouseOver="krumo.over(this);" onMouseOut="krumo.out(this);">
            <a class="krumo-name"><?php echo $name;?></a>
            (<em class="krumo-type">Closure</em>)
            <strong class="krumo-class"><?php echo get_class($data);?></strong>
        </div>
        <?php $this->_vars($this->_getFunction($data), $isExpanded); ?>
    </li>
    <?php
    }

    /**
     * Render HTML for max depth message
     * @param $var
     * @param $name
     * @return void
     */
    protected function _maxDepth($var, $name)
    {
        unset($var);
        $this->_renderNode('max depth', $name, '(<span style="color:red">!</span>) Max depth');
    }

    /**
     * Render HTML for undefined variable
     * @param   mixed   $var   Variable
     * @param   string  $name  Variable name
     * @return  void
     */
    protected function _undefined($var, $name)
    {
        $this->_renderNode('undefined', $name, '(<span style="color:red">!</span>) getType = ' . gettype($var));
    }

    /**
     * Render HTML for undefined variable
     * @param   string  $type   Variable type
     * @param   mixed   $data   Variable
     * @param   string  $name   Variable name
     * @return  void
     */
    protected function _renderNode($type, $name, $data)
    {
        ?>
    <li class="krumo-child">
        <div class="krumo-element" onMouseOver="krumo.over(this);" onMouseOut="krumo.out(this);">
            <a class="krumo-name"><?php echo $name;?></a>
            (<em class="krumo-type"><?php echo $type;?></em>)
            <strong class="krumo-<?php echo strtolower($type);?>"><?php echo $data;?></strong>
        </div>
    </li>
    <?php
    }

    /**
     * Get the IP number of differnt ways
     * @static
     * @param bool $getSource
     * @return string
     */
    public static function getClientIP($getSource = false)
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     = $_SERVER['HTTP_CLIENT_IP'];
            $source = 'HTTP_CLIENT_IP';

        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip     = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $source = 'HTTP_X_FORWARDED_FOR';

        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip     = $_SERVER['HTTP_X_REAL_IP'];
            $source = 'HTTP_X_REAL_IP';

        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip     = $_SERVER['REMOTE_ADDR'];
            $source = 'REMOTE_ADDR';

        } else {
            $ip     = 'undefined';
            $source = 'undefined';
        }

        if ($getSource) {
            return $source;
        } else {
            return $ip;
        }
    }

    /**
     * Get relative path from absolute
     * @param   string  $path   Absolute filepath
     * @return  string
     */
    protected function _getRalativePath($path)
    {
        if ($path) {
            $rootPath = str_replace(array('/', '\\'), '/', self::$_config['root']);
        
            $path = str_replace(array('/', '\\'), '/', $path);
            $path = str_replace($rootPath, '/', $path);
            $path = str_replace('//', '/', $path);
            $path = trim($path, '/');
        }
        return $path;
    }

    /**
     * Get formated one trace info
     * @param   array   $info      One trace element
     * @param   bool    $addObject OPTIONAL Add object to result (low perfomance)
     * @return  array
     */
    protected function _getOneTrace($info, $addObject = false)
    {
        $_this = self::i();

        $_tmp = array();
        if (isset($info['file'])) {
            $_tmp['file'] = $_this->_getRalativePath($info['file']) . ' : ' . $info['line'];
        } else {
            $info['file'] = false;
        }

        if ($info['function'] != 'include' && $info['function'] != 'include_once' && $info['function'] != 'require'
                && $info['function'] != 'require_once'
        ) {
            if (isset($info['type']) && isset($info['class'])) {

                $_tmp['func'] = $info['class']
                        . ' ' . $info['type']
                        . ' ' . $info['function']
                        . '(' . @count($info['args']) . ')';
            } else {
                $_tmp['func'] = $info['function']
                        . '(' . @count($info['args']) . ')';
            }

            $args = isset($info['args']) ? $info['args'] : array();

            if (self::$_config['showArgs'] || $addObject) {
                $_tmp['args'] = isset($info['args']) ? $info['args'] : array();
            } else {
                $_tmp['count_args'] = count($args);
            }

        } else {
            $_tmp['func'] = $info['function'];
        }

        if (isset($info['object']) && (self::$_config['showArgs'] || $addObject)) {
            $_tmp['obj'] = $info['object'];
        }

        return $_tmp;
    }

    /**
     * Convert filesize to formated string
     * @param   integer $bytes  Count bytes
     * @return  string
     */
    protected static function _formatSize($bytes)
    {
        $exp    = 0;
        $value  = 0;
        $symbol = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

        if ($bytes > 0) {
            $exp   = floor(log($bytes) / log(1024));
            $value = ($bytes / pow(1024, floor($exp)));
        }

        return sprintf('%.2f ' . $symbol[$exp], $value);
    }

    /**
     * Include css and js files in document
     * @param bool $force
     * @return void
     */
    protected function _initAssets($force = true)
    {
        static $loaded;
        if (!isset($loaded) || $force) {
            $loaded = true;

            echo '
            <script type="text/javascript">
                function krumo(){}
                krumo.reclass=function(el,className){if(el.className.indexOf(className)<0)el.className+=" "+className};
                krumo.unclass=function(el,className){if(el.className.indexOf(className)>-1)el.className=el.className.replace(className,"")};
                krumo.toggle=function(el){var ul=el.parentNode.getElementsByTagName("ul");for(var i=0;i<ul.length;i++)if(ul[i].parentNode.parentNode==el.parentNode)ul[i].parentNode.style.display=ul[i].parentNode.style.display=="none"?"block":"none";if(ul[0].parentNode.style.display=="block")krumo.reclass(el,"krumo-opened");else krumo.unclass(el,"krumo-opened")};
                krumo.over=function(el){krumo.reclass(el,"krumo-hover")};
                krumo.out=function(el){krumo.unclass(el,"krumo-hover")};
            </script>';

            echo '
            <style type="text/css">
                ul.krumo-node{background-color:#fff!important;color:#333!important;list-style:none;text-align:left!important;margin:0!important;padding:0;}
                ul.krumo-node ul.krumo-node{margin-left:15px!important;}
                ul.krumo-node pre, ul.krumo-node textarea{font-size:92%;font-family:Courier,Monaco,"Lucida Console";width:100%;background:inherit!important;color:#000;border:none;margin:0;padding:0;}
                ul.krumo-node textarea{height: 80%;min-height: 250px;text-align:left!important;}
                ul.krumo-node ul{margin-left:20px;}
                ul.krumo-node li{list-style:none;line-height:12px!important;margin:0 0 0 5px!important;min-height:12px!important;height:auto!important;}
				div.krumo-root{border:solid 1px #000;position:relative;z-index:10101;min-width:400px;margin:5px 0 20px;clear: both;}
                ul.krumo-first{font:normal 10px tahoma, verdana;border:solid 1px #FFF;}
				div.krumo-root *{opacity:1!important;font-size:12px!important;}
                li.krumo-child{display:block;list-style:none;overflow:hidden;margin:0;padding:0;}
                div.krumo-element{cursor:default;display:block;clear:both;white-space:nowrap;background-color:#FFF;background-image:url(data:;base64,R0lGODlhCQAJALMAAP////8AAICAgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAJAAkAAAQSEAAhq6VWUpx3n+AVVl42ilkEADs=);background-repeat:no-repeat;background-position:6px 5px;padding:2px 0 3px 20px;}
                div.krumo-expand{background-image:url(data:;base64,R0lGODlhCQAJALMAAP///wAAAP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAJAAkAAAQTEIAna33USpwt79vncRpZgpcGRAA7);cursor:pointer;}
                div.krumo-hover{background-color:#BFDFFF;}
                div.krumo-opened{background-image:url(data:;base64,R0lGODlhCQAJALMAAP///wAAAP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAJAAkAAAQQEMhJ63w4Z6C37JUXWmQJRAA7);}
                a.krumo-name{color:#a00;font:14px courier new;line-height:12px;text-decoration:none;}
                a.krumo-name big{font:bold 14px Georgia;line-height:10px;position:relative;top:2px;left:-2px;}
                em.krumo-type{font-style:normal;margin:0 2px;}
                div.krumo-preview{font:normal 13px courier new;background:#F9F9B5;border:solid 1px olive;overflow:auto;margin:5px 1em 1em 0;padding:5px;}
                li.krumo-footnote{background:#FFF url(data:;base64,R0lGODlhCgACALMAAP///8DAwP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAKAAIAAAQIEMhJA7D4gggAOw==) repeat-x;list-style:none;cursor:default;padding:4px 5px 3px;}
                li.krumo-footnote h6{font:bold 10px verdana;color:navy;display:inline;margin:0;padding:0;}
                li.krumo-footnote a{font:bold 10px arial;color:#434343;text-decoration:none;}
                li.krumo-footnote a:hover{color:#000;}
                li.krumo-footnote span.krumo-call{font-size:11px;font-family:Courier,Monaco,"Lucida Console";position:relative;top:1px;}
                li.krumo-footnote span.krumo-call code{font-weight:700;}
                div.krumo-title{font:normal 11px Tahoma, Verdana;position:relative;top:9px;cursor:default;line-height:2px;}
                strong.krumo-array-length,strong.krumo-string-length{font-weight:400;color:#009;}
                .krumo-footnote .copyrights a{color:#ccc;font-size:8px;}
                div.krumo-version,.krumo-footnote .copyrights{float:right;}
                pre.krumo {text-align:left!important;}
                #jbdump_profile_chart_table td img {height: 12px!important;}
                #jbdump_profile_chart_table {color:#333!important;}
            </style>';
        }
    }

    /**
     * Get last funtcion name and it params from backtarce
     * @param   array  $trace  Backtrace
     * @return  string
     */
    protected function _getSourceFunction($trace)
    {
        $lastTrace = $this->_getLastTrace($trace);

        if (isset($lastTrace['function']) || isset($lastTrace['class'])) {

            $args = '';
            if (isset($lastTrace['args'])) {
                $args = '( ' . count($lastTrace['args']) . ' args' . ' )';
            }

            if (isset($lastTrace['class'])) {
                $function = $lastTrace['class'] . ' ' . $lastTrace['type'] . ' ' . $lastTrace['function'] . ' ' . $args;
            } else {
                $function = $lastTrace['function'] . ' ' . $args;
            }

            return 'Function: ' . $function . '<br />';
        }

        return '';
    }

    /**
     * Get last source path from backtrace
     * @param   array  $trace    Backtrace
     * @param   bool   $fileOnly Show filename only
     * @return  string
     */
    protected function _getSourcePath($trace, $fileOnly = false)
    {
        $path         = '';
        $currentTrace = $this->_getLastTrace($trace);

        if (isset($currentTrace['file'])) {
            $path = $this->_getRalativePath($currentTrace['file']);

            if ($fileOnly && $path) {
                $path = pathinfo($path, PATHINFO_BASENAME);
            }

            if (isset($currentTrace['line']) && $path) {
                $path = $path . ':' . $currentTrace['line'];
            }
        }

        if (!$path) {
            $path = 'undefined:0';
        }

        return $path;
    }

    /**
     * Get Last trace info
     * @param   array   $trace Backtrace
     * @return  array
     */
    protected function _getLastTrace($trace)
    {
        // current filename info
        $curFile       = pathinfo(__FILE__, PATHINFO_BASENAME);
        $curFileLength = strlen($curFile);

        $meta = array();
        $j    = 0;
        for ($i = 0; $trace && $i < sizeof($trace); $i++) {
            $j = $i;
            if (isset($trace[$i]['class'])
                    && isset($trace[$i]['file'])
                    && ($trace[$i]['class'] == 'JBDump')
                    && (substr($trace[$i]['file'], -$curFileLength, $curFileLength) == $curFile)
            ) {

            } elseif (isset($trace[$i]['class'])
                    && isset($trace[$i + 1]['file'])
                    && isset($trace[$i]['file'])
                    && $trace[$i]['class'] == 'JBDump'
                    && (substr($trace[$i]['file'], -$curFileLength, $curFileLength) == $curFile)
            ) {

            } elseif (isset($trace[$i]['file'])
                    && (substr($trace[$i]['file'], -$curFileLength, $curFileLength) == $curFile)
            ) {

            } else {
                // found!
                $meta['file'] = isset($trace[$i]['file']) ? $trace[$i]['file'] : '';
                $meta['line'] = isset($trace[$i]['line']) ? $trace[$i]['line'] : '';
                break;
            }
        }

        // get functions
        if (isset($trace[$j + 1])) {
            $result         = $trace[$j + 1];
            $result['line'] = $meta['line'];
            $result['file'] = $meta['file'];
        } else {
            $result = $meta;
        }

        return $result;
    }

    /**
     * Get object methods
     * @param   object  $object    Backtrace
     * @return  array
     */
    protected function _getMethods($object)
    {
        if (is_string($object)) {
            $className = $object;
        } else {
            $className = get_class($object);
        }
        $methods = get_class_methods($className);

        if (self::$_config['sort']['methods']) {
            sort($methods);
        }
        return $methods;
    }

    /**
     * Get all info about class (object)
     * @param   string|object  $data    Object or class name
     * @return  JBDump
     */
    protected static function _getClass($data)
    {
        // check arg
        if (is_object($data)) {
            $className = get_class($data);
        } elseif (is_string($data)) {
            $className = $data;
        } else {
            return false;
        }

        if (!class_exists($className) && !interface_exists($className)) {
            return false;
        }

        // create ReflectionClass object
        $class = new ReflectionClass($data);

        // get basic class info
        $result['name'] = $class->name;
        $result['type'] = ($class->isInterface() ? 'interface' : 'class');
        if ($classComment = $class->getDocComment()) {
            $result['comment'] = $classComment;
        }
        if ($classPath = $class->getFileName()) {
            $result['path'] = $classPath . ' ' . $class->getStartLine() . '/' . $class->getEndLine();
        }
        if ($classExtName = $class->getExtensionName()) {
            $result['extension'] = $classExtName;
        }
        if ($class->isAbstract()) {
            $result['abstract'] = true;
        }
        if ($class->isFinal()) {
            $result['final'] = true;
        }

        // get all parents of class
        $class_tmp         = $class;
        $result['parents'] = array();
        while ($parent = $class_tmp->getParentClass()) {
            if (isset($parent->name)) {
                $result['parents'][] = $parent->name;
                $class_tmp           = $parent;
            }
        }
        if (count($result['parents']) == 0) {
            unset($result['parents']);
        }

        // reflecting class interfaces
        $interfaces = $class->getInterfaces();
        if (is_array($interfaces)) {
            foreach ($interfaces as $property) {
                $result['interfaces'][] = $property->name;
            }
        }

        // reflection class constants
        $constants = $class->getConstants();
        if (is_array($constants)) {
            foreach ($constants as $key => $property) {
                $result['constants'][$key] = $property;
            }
        }

        // reflecting class properties 
        $properties = $class->getProperties();
        if (is_array($properties)) {
            foreach ($properties as $key => $property) {

                if ($property->isPublic()) {
                    $visible = "public";
                } elseif ($property->isProtected()) {
                    $visible = "protected";
                } elseif ($property->isPrivate()) {
                    $visible = "private";
                } else {
                    $visible = "public";
                }

                $propertyName = $property->getName();

                $result['properties'][$visible][$property->name]['comment'] = $property->getDocComment();
                $result['properties'][$visible][$property->name]['static']  = $property->isStatic();
                $result['properties'][$visible][$property->name]['default'] = $property->isDefault();
                $result['properties'][$visible][$property->name]['class']   = $property->class;
            }
        }

        // get source
        $source = null;
        if (isset($result['path']) && $result['path']) {
            $source = @file($class->getFileName());
            if (!empty($source)) {
                $result['source::source'] = implode('', $source);
            }
        }

        // reflecting class methods 
        foreach ($class->getMethods() as $key => $method) {

            if ($method->isPublic()) {
                $visible = "public";
            } elseif ($method->isProtected()) {
                $visible = "protected";
            } elseif ($method->isPrivate()) {
                $visible = "protected";
            } else {
                $visible = "public";
            }

            $result['methods'][$visible][$method->name]['name'] = $method->getName();

            if ($method->isAbstract()) {
                $result['methods'][$visible][$method->name]['abstract'] = true;
            }
            if ($method->isFinal()) {
                $result['methods'][$visible][$method->name]['final'] = true;
            }
            if ($method->isInternal()) {
                $result['methods'][$visible][$method->name]['internal'] = true;
            }
            if ($method->isStatic()) {
                $result['methods'][$visible][$method->name]['static'] = true;
            }
            if ($method->isConstructor()) {
                $result['methods'][$visible][$method->name]['constructor'] = true;
            }
            if ($method->isDestructor()) {
                $result['methods'][$visible][$method->name]['destructor'] = true;
            }
            $result['methods'][$visible][$method->name]['declaringClass'] = $method->getDeclaringClass()->name;

            if ($comment = $method->getDocComment()) {
                $result['methods'][$visible][$method->name]['comment'] = $comment;
            }

            $startLine = $method->getStartLine();
            $endLine   = $method->getEndLine();
            if ($startLine && $source) {
                $from    = (int)($startLine - 1);
                $to      = (int)($endLine - $startLine + 1);
                $slice   = array_slice($source, $from, $to);
                $phpCode = implode('', $slice);

                $result['methods'][$visible][$method->name]['source::source'] = $phpCode;
            }

            if ($params = self::_getParams($method->getParameters(), $method->isInternal())) {
                $result['methods'][$visible][$method->name]['parameters'] = $params;
            }
        }

        // get all methods
        $result['all_methods'] = get_class_methods($className);
        sort($result['all_methods']);

        // sorting properties and methods
        if (isset($result['properties']['protected'])) {
            ksort($result['properties']['protected']);
        }
        if (isset($result['properties']['private'])) {
            ksort($result['properties']['private']);
        }
        if (isset($result['properties']['public'])) {
            ksort($result['properties']['public']);
        }
        if (isset($result['methods']['protected'])) {
            ksort($result['methods']['protected']);
        }
        if (isset($result['methods']['private'])) {
            ksort($result['methods']['private']);
        }
        if (isset($result['methods']['public'])) {
            ksort($result['methods']['public']);
        }

        return $result;
    }

    /**
     * Get function/method params info
     * @param      $params Array of ReflectionParameter
     * @param bool $isInternal
     * @return array
     */
    protected static function _getParams($params, $isInternal = true)
    {

        if (!is_array($params)) {
            $params = array($params);
        }

        $result = array();
        foreach ($params as $param) {
            $optional                   = $param->isOptional();
            $paramName                  = (!$optional ? '*' : '') . $param->name;
            $result[$paramName]['name'] = $param->getName();
            if ($optional && !$isInternal) {
                $result[$paramName]['default'] = $param->getDefaultValue();
            }
            if ($param->allowsNull()) {
                $result[$paramName]['null'] = true;
            }
            if ($param->isArray()) {
                $result[$paramName]['array'] = true;
            }
            if ($param->isPassedByReference()) {
                $result[$paramName]['reference'] = true;
            }
        }

        return $result;
    }

    /**
     * Get all info about function
     * @static
     * @param   string|function $functionName Function or function name
     * @return  array|bool
     */
    protected static function _getFunction($functionName)
    {
        if (is_string($functionName) && !function_exists($functionName)) {
            return false;

        } elseif (empty($functionName)) {

            return false;
        }

        // create ReflectionFunction instance
        $func = new ReflectionFunction($functionName);

        // get basic function info
        $result         = array();
        $result['name'] = $func->getName();
        $result['type'] = $func->isInternal() ? 'internal' : 'user-defined';
        
        if (method_exists($func, 'getNamespaceName') && $namespace = $func->getNamespaceName()) {
            $result['namespace'] = $namespace;
        }
        if ($func->isDeprecated()) {
            $result['deprecated'] = true;
        }
        if ($static = $func->getStaticVariables()) {
            $result['static'] = $static;
        }
        if ($reference = $func->returnsReference()) {
            $result['reference'] = $reference;
        }
        if ($path = $func->getFileName()) {
            $result['path'] = $path . ' ' . $func->getStartLine() . '/' . $func->getEndLine();
        }
        if ($parameters = $func->getParameters()) {
            $result['parameters'] = self::_getParams($parameters, $func->isInternal());
        }

        // get function source
        if (isset($result['path']) && $result['path']) {
            $result['comment'] = $func->getDocComment();

            $startLine = $func->getStartLine();
            $endLine   = $func->getEndLine();
            $source    = @file($func->getFileName());

            if ($startLine && $source) {

                $from  = (int)($startLine - 1);
                $to    = (int)($endLine - $startLine + 1);
                $slice = array_slice($source, $from, $to);

                $result['source::source'] = implode('', $slice);

            }
        }

        return $result;
    }

    /**
     * Get all info about function
     * @static
     * @param string|function  $extensionName    Function or function name
     * @return array|bool
     */
    protected static function _getExtension($extensionName)
    {
        if (!extension_loaded($extensionName)) {
            return false;
        }

        $ext    = new ReflectionExtension($extensionName);
        $result = array();

        $result['name']    = $ext->name;
        $result['version'] = $ext->getVersion();
        if ($constants = $ext->getConstants()) {
            $result['constants'] = $constants;
        }
        if ($classesName = $ext->getClassNames()) {
            $result['classesName'] = $classesName;
        }
        if ($functions = $ext->getFunctions()) {
            $result['functions'] = $functions;
        }
        if ($dependencies = $ext->getDependencies()) {
            $result['dependencies'] = $dependencies;
        }
        if ($INIEntries = $ext->getINIEntries()) {
            $result['INIEntries'] = $INIEntries;
        }

        $functions = $ext->getFunctions();
        if (is_array($functions) && count($functions) > 0) {
            $result['functions'] = array();
            foreach ($functions as $function) {
                $funcName                       = $function->getName();
                $result['functions'][$funcName] = self::_getFunction($funcName);
            }
        }

        return $result;
    }

    /**
     * Get all file info
     * @param   string  $path
     * @return  array|bool
     */
    protected static function _pathInfo($path)
    {
        $result = array();

        $filename = realpath($path);

        $result['realpath'] = $filename;
        $result             = array_merge($result, pathinfo($filename));

        $result['type']  = filetype($filename);
        $result['exist'] = file_exists($filename);
        if ($result['exist']) {

            $result['time created']  = filectime($filename) . ' / ' . date(self::DATE_FORMAT, filectime($filename));
            $result['time modified'] = filemtime($filename) . ' / ' . date(self::DATE_FORMAT, filemtime($filename));
            $result['time access']   = fileatime($filename) . ' / ' . date(self::DATE_FORMAT, fileatime($filename));

            $result['group'] = filegroup($filename);
            $result['inode'] = fileinode($filename);
            $result['owner'] = fileowner($filename);
            $perms           = fileperms($filename);

            if (($perms & 0xC000) == 0xC000) { // Socket
                $info = 's';
            } elseif (($perms & 0xA000) == 0xA000) { // Symbolic Link
                $info = 'l';
            } elseif (($perms & 0x8000) == 0x8000) { // Regular
                $info = '-';
            } elseif (($perms & 0x6000) == 0x6000) { // Block special
                $info = 'b';
            } elseif (($perms & 0x4000) == 0x4000) { // Directory
                $info = 'd';
            } elseif (($perms & 0x2000) == 0x2000) { // Character special
                $info = 'c';
            } elseif (($perms & 0x1000) == 0x1000) { // FIFO pipe
                $info = 'p';
            } else { // Unknown
                $info = 'u';
            }

            // owner
            $info .= (($perms & 0x0100) ? 'r' : '-');
            $info .= (($perms & 0x0080) ? 'w' : '-');
            $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));

            // group
            $info .= (($perms & 0x0020) ? 'r' : '-');
            $info .= (($perms & 0x0010) ? 'w' : '-');
            $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));

            // other
            $info .= (($perms & 0x0004) ? 'r' : '-');
            $info .= (($perms & 0x0002) ? 'w' : '-');
            $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

            $result['perms'] = $perms . ' / ' . $info;

            $result['is_readable'] = is_readable($path);
            $result['is_writable'] = is_writable($path);

            if ($result['type'] == 'file') {

                $size = filesize($filename);

                $result['size'] = $size . ' / ' . self::_formatSize($size);
            }

        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * Convert trace infomation to readable
     * @param array $trace Standart debug backtrace data
     * @param bool  $addObject
     * @return array
     */
    public function convertTrace($trace, $addObject = false)
    {
        $result = array();
        foreach ($trace as $key => $info) {
            $oneTrace = self::i()->_getOneTrace($info, $addObject);

            //$result['#' . ($key - 1) . ' ' . $oneTrace['func']] = $oneTrace;
            $result['#' . ($key - 1) . ' ' . $oneTrace['func']] = $oneTrace['file'];
        }

        return $result;
    }

    /**
     * Get PHP error types
     * @return  array
     */
    protected static function _getErrorTypes()
    {
        $errType = array(
            E_ERROR             => 'Error',
            E_WARNING           => 'Warning',
            E_PARSE             => 'Parsing Error',
            E_NOTICE            => 'Notice',
            E_CORE_ERROR        => 'Core Error',
            E_CORE_WARNING      => 'Core Warning',
            E_COMPILE_ERROR     => 'Compile Error',
            E_COMPILE_WARNING   => 'Compile Warning',
            E_USER_ERROR        => 'User Error',
            E_USER_WARNING      => 'User Warning',
            E_USER_NOTICE       => 'User Notice',
            E_STRICT            => 'Runtime Notice',
            E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        );

        if (defined('E_DEPRECATED')) {
            $errType[E_DEPRECATED]      = 'Deprecated';
            $errType[E_USER_DEPRECATED] = 'User Deprecated';
        }

        $errType[E_ALL] = 'All errors';

        return $errType;
    }

    /**
     * Error handler for PHP errors
     * @param   integer $errNo
     * @param   string  $errMsg
     * @param   string  $errFile
     * @param   integer $errLine
     * @param   array   $errCont
     * @return  bool
     */
    function _errorHandler($errNo, $errMsg, $errFile, $errLine, $errCont)
    {
        $errType = $this->_getErrorTypes();

        $errorMessage = $errType[$errNo] . "\t\"" . trim($errMsg) . "\"\t" . $errFile . ' ' . 'Line:' . $errLine;

        if (self::$_config['errors']['logAll']) {
            error_log('JBDump:' . $errorMessage);
        }

        if (!(error_reporting() & $errNo) || error_reporting() == 0 || (int)ini_get('display_errors') == 0) {

            if (self::$_config['errors']['logHidden']) {
                $errorMessage = date(self::DATE_FORMAT, time()) . ' ' . $errorMessage . "\n";

                $logPath      = self::$_config['log']['path']
                                . '/' . self::$_config['log']['file'] . '_error_' . date('Y.m.d') . '.log';

                error_log($errorMessage, 3, $logPath);
            }

            return false;
        }


        $errFile = $this->_getRalativePath($errFile);
        $result  = array(
            'file'    => $errFile . ' : ' . $errLine,
            'type'    => $errType[$errNo] . ' (' . $errNo . ')',
            'message' => $errMsg,
        );

        if (self::$_config['errors']['context']) {
            $result['context'] = $errCont;
        }

        if (self::$_config['errors']['errorBacktrace']) {
            $trace = debug_backtrace();
            unset($trace[0]);
            $result['backtrace'] = $this->convertTrace($trace);
        }

        if ($this->_isLiteMode()) {
            $errorInfo = array(
                'message' => $result['type'] . ' / ' . $result['message'],
                'file'    => $result['file']
            );
            $this->_dumpRenderLite($errorInfo, '* ' . $errType[$errNo]);

        } else {
            $desc = '<b style="color:red;">*</b> ' . $errType[$errNo] . ' / ' . htmlSpecialChars($result['message']);
            $this->dump($result, $desc);
        }

        return true;
    }

    /**
     * Exception handler
     * @param   Exception   $exception PHP exception object
     * @return  boolean
     */
    function _exceptionHandler($exception)
    {
        $result['message'] = $exception->getMessage();

        if (self::$_config['errors']['exceptionBacktrace']) {
            $result['backtrace'] = $this->convertTrace($exception->getTrace());
        }

        $result['string'] = $exception->getTraceAsString();
        $result['code']   = $exception->getCode();

        if ($this->_isLiteMode()) {
            $this->_dumpRenderLite("\n" . $result['string'], '** EXCEPTION / ' . htmlSpecialChars($result['message']));

        } else {
            $this->_initAssets(true);
            $this->dump($result, '<b style="color:red;">**</b> EXCEPTION / ' . htmlSpecialChars($result['message']));
        }

        return true;
    }

    /**
     * Information about current PHP reporting
     * @return  JBDump
     */
    public static function errors()
    {
        $result                    = array();
        $result['error_reporting'] = error_reporting();
        $errTypes                  = self::_getErrorTypes();

        foreach ($errTypes as $errTypeKey => $errTypeName) {
            if ($result['error_reporting'] & $errTypeKey) {
                $result['show_types'][] = $errTypeName . ' (' . $errTypeKey . ')';
            }
        }

        return self::i()->dump($result, '! errors info !');
    }

    /**
     * Is current request ajax or lite mode is enabled
     * @return  bool
     */
    protected function _isLiteMode()
    {
        if (self::$_config['dump']['render'] == 'lite') {
            return true;
        }

        return self::isAjax();
    }

    /**
     * Check is current HTTP request is ajax
     * @static
     * @return  bool
     */
    public static function isAjax()
    {

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            return true;

        } elseif (php_sapi_name() == 'cli') {
            return true;

        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $key => $value) {
                if (strtolower($key) == 'x-requested-with' && strtolower($value) == 'xmlhttprequest') {
                    return true;
                }
            }

        } elseif (isset($_REQUEST['ajax']) && $_REQUEST['ajax']) {
            return true;

        } elseif (isset($_REQUEST['AJAX']) && $_REQUEST['AJAX']) {
            return true;

        }

        return false;
    }

    /**
     * Send message mail
     * @static
     * @param mixed  $text
     * @param string $subject
     * @param string $to
     * @return bool
     */
    public static function mail($text, $subject = null, $to = null)
    {
        if (!self::isDebug()) {
            return false;
        }

        $_this = self::i();

        if (empty($subject)) {
            $subject = self::$_config['mail']['subject'];
        }

        if (empty($to)) {
            $to = isset(self::$_config['mail']['to'])
                    ? self::$_config['mail']['to']
                    : 'jbdump@' . $_SERVER['HTTP_HOST'];
        }

        if (is_array($to)) {
            $to = implode(', ', $to);
        }

        // message
        $message   = array();
        $message[] = '<html><body>';
        $message[] = '<p><b>JBDump mail from '
                . '<a href="http://' . $_SERVER['HTTP_HOST'] . '">' . $_SERVER['HTTP_HOST'] . '</a>'
                . '</b></p>';

        $message[] = '<p><b>Date</b>: ' . date(DATE_RFC822, time()) . '</p>';
        $message[] = '<p><b>IP</b>: ' . self::getClientIP() . '</p>';
        $message[] = '<b>Debug message</b>: <pre>' . print_r($text, true) . '</pre>';
        $message[] = '</body></html>';
        $message   = wordwrap(implode("\n", $message), 70);

        // To send HTML mail, the Content-type header must be set
        $headers   = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
        $headers[] = 'To: ' . $to;
        $headers[] = 'From: JBDump debug <jbdump@' . $_SERVER['HTTP_HOST'] . '>';
        $headers[] = 'X-Mailer: JBDump v' . self::VERSION;
        $headers   = implode("\r\n", $headers);

        $result = mail($to, $subject, $message, $headers);
        if (self::$_config['mail']['log']) {
            $_this->log(
                array(
                    'email'   => $to,
                    'subject' => $subject,
                    'message' => $message,
                    'headers' => $headers,
                    'result'  => $result
                ),
                'JBDump::mail'
            );
        }

        return $result;
    }

    /**
     * Get arguments for current function/method
     * @static
     * @return bool
     */
    public static function args()
    {
        if (!self::isDebug()) {
            return false;
        }

        $_this = self::i();

        $trace        = debug_backtrace(0);
        $currentTrace = $trace[1];
        if (isset($currentTrace['args'])) {

            // get function info (class method or simple func)
            if (isset($currentTrace['class'])) {

                $classInfo = $_this->_getClass($currentTrace['class']);
                if (isset($classInfo['methods']['public'][$currentTrace['function']])) {
                    $funcInfo = $classInfo['methods']['public'][$currentTrace['function']];

                } elseif (isset($classInfo['methods']['private'][$currentTrace['function']])) {
                    $funcInfo = $classInfo['methods']['private'][$currentTrace['function']];

                } elseif (isset($classInfo['methods']['protected'][$currentTrace['function']])) {
                    $funcInfo = $classInfo['methods']['protected'][$currentTrace['function']];
                }

            } else {
                $funcInfo = $_this->_getFunction($currentTrace['function']);
            }

            // chech arguments info
            if (isset($funcInfo['parameters'])) {
                $result = array();
                $i      = 0;
                foreach ($funcInfo['parameters'] as $argName=> $argInfo) {

                    if (isset($currentTrace['args'][$i])) {
                        $result[$argName] = $currentTrace['args'][$i];

                    } elseif (isset($argInfo['default'])) {
                        $result[$argName] = $argInfo['default'];

                    } else {
                        $result[$argName] = null;
                    }

                    $i++;
                }

            } else {
                $result = $currentTrace['args'];
            }

            $_this->dump($result);
        }

        return $_this;
    }

    /**
     * Highlight SQL query
     * @static
     * @param        $query
     * @param string $sqlName
     * @param bool   $nl2br
     * @return JBDump
     */
    public static function sql($query, $sqlName = 'SQL Query', $nl2br = false)
    {
        if (defined('_JEXEC')) {
            $config = new JConfig();
            $prefix = $config->dbprefix;
            $query = str_replace('#__', $prefix, $query);
        }        
        
        if (class_exists('SqlFormatter')) {
            $sqlHtml = SqlFormatter::format($query);
            
            return self::i()->dump($sqlHtml, $sqlName . '::html');
        }
    
        $tmp = htmlspecialchars((string)$query);
        $tmp = str_replace("\r", '', $tmp);
        $tmp = trim(str_replace("\n", "\r\n", $tmp)) . "\r\n";

        $quote_list_text    = array();
        $quote_list_symbols = array();

        $k      = 0;
        $quotes = array();

        preg_match_all("/\\\'|\\\&quot;/is", $tmp, $quotes);
        array_unique($quotes);
        if (count($quotes)) {
            foreach ($quotes[0] as $i) {
                $k++;
                $quote_list_symbols[$k] = $i;
                $tmp                    = str_replace($i, '<symbol' . $k . '>', $tmp);
            }
        }

        $matches = array(
            "/(&quot;|'|`)(.*?)(\\1)/is", // test in quotes
            "/\/\*.*?\*\//s", // text on comments
            "/ \-\-.*\x0D\x0A/", // text ' --' comment
            "/ #.*\x0D\x0A/", // text ' #' comment
        );

        foreach ($matches as $match) {
            $found = array();
            preg_match_all($match, $tmp, $found);
            $quotes = (array)$found[0];
            array_unique($quotes);
            if (count($quotes)) {
                foreach ($quotes as $i) {
                    $k++;
                    $quote_list_text[$k] = $i;
                    $tmp                 = str_replace($i, '<text' . $k . '>', $tmp);
                }
            }
        }

        $keywords = array(
            "avg", "as", "auto_increment", "and", "analyze", "alter",
            "asc", "all", "after", "add", "action", "against",
            "aes_encrypt", "aes_decrypt", "ascii", "abs", "acos",
            "asin", "atan", "authors", "between", "btree", "backup",
            "by", "binary", "before", "binlog", "benchmark", "blob",
            "bigint", "bit_count", "bit_or", "bit_and", "bin",
            "bit_length", "both", "create", "count", "comment",
            "check", "char", "concat", "cipher", "changed", "column",
            "columns", "change", "constraint", "cascade", "checksum",
            "cross", "close", "concurrent", "commit", "curdate",
            "current_date", "curtime", "current_time",
            "current_timestamp", "cast", "convert", "connection_id",
            "coalesce", "case", "conv", "concat_ws", "char_length",
            "character_length", "ceiling", "cos", "cot", "crc32",
            "compress", "delete", "drop", "default", "distinct",
            "decimal", "date", "describe", "data", "desc",
            "dayofmonth", "date_add", "database", "databases",
            "double", "duplicate", "disable", "datetime", "dumpfile",
            "distinctrow", "delayed", "dayofweek", "dayofyear",
            "dayname", "day_minute", "date_format", "date_sub",
            "decode", "des_encrypt", "des_decrypt", "degrees",
            "decompress", "dec", "engine", "explain", "enum",
            "escaped", "execute", "extended", "errors", "exists",
            "enable", "enclosed", "extract", "encrypt", "encode",
            "elt", "export_set", "escape", "exp", "end", "from",
            "float", "flush", "fields", "file", "for", "fast", "full",
            "fulltext", "first", "foreign", "force", "from_days",
            "from_unixtime", "format", "found_rows", "floor", "field",
            "find_in_set", "group", "grant", "grants", "global",
            "get_lock", "greatest", "having", "high_priority",
            "handler", "hour", "hex", "insert", "into", "inner",
            "int", "ifnull", "if", "isnull", "in", "infile", "is",
            "interval", "ignore", "identified", "index", "issuer",
            "integer", "is_free_lock", "inet_ntoa", "inet_aton",
            "instr", "join", "kill", "key", "keys", "left", "load",
            "local", "limit", "like", "lock", "lpad", "last_insert_id",
            "logs", "length", "longblob", "longtext", "last", "lines",
            "low_priority", "locate", "ltrim", "leading", "lcase",
            "lower", "load_file", "ln", "log", "least", "month", "mod",
            "max", "min", "mediumint", "medium", "master", "modify",
            "mediumblob", "mediumtext", "match", "mode", "monthname",
            "mid", "minute", "master_pos_wait", "make_set", "null",
            "not", "now", "none", "new", "numeric", "no", "natural",
            "next", "nullif", "national", "nchar", "on", "or",
            "optimize", "order", "optionally", "option", "outfile",
            "open", "offset", "outer", "old_password", "ord", "oct",
            "octet_length", "primary", "password", "privileges",
            "process", "processlist", "purge", "partial", "procedure",
            "prev", "period_add", "period_diff", "position", "pow",
            "power", "pi", "quick", "quarter", "quote", "right",
            "repair", "restore", "reset", "regexp", "references",
            "replace", "revoke", "reload", "require", "replication",
            "read", "rand", "rename", "real", "restrict",
            "release_lock", "rpad", "rtrim", "repeat", "reverse",
            "rlike", "round", "radians", "rollup", "select", "sum",
            "set", "show", "substring", "smallint", "super", "subject",
            "status", "slave", "session", "start", "share",
            "straight_join", "sql_small_result", "sql_big_result",
            "sql_buffer_result", "sql_cache", "sql_no_cache",
            "sql_calc_found_rows", "second", "sysdate", "sec_to_time",
            "system_user", "session_user", "substring_index", "std",
            "stddev", "soundex", "space", "strcmp", "sign", "sqrt",
            "sin", "straight", "sleep", "text", "truncate", "table",
            "tinyint", "tables", "to_days", "temporary", "terminated",
            "to", "types", "time", "timestamp", "tinytext",
            "tinyblob", "transaction", "time_format", "time_to_sec",
            "trim", "trailing", "tan", "then", "update", "union",
            "using", "unsigned", "unlock", "usage", "use_frm",
            "unix_timestamp", "unique", "use", "user", "ucase",
            "upper", "uuid", "values", "varchar", "variables",
            "version", "variance", "varying", "where", "with",
            "warnings", "write", "weekday", "week", "when", "xor",
            "year", "yearweek", "year_month", "zerofill"
        );

        $replace = Array();
        foreach ($keywords as $keyword) {
            $replace[] = '/\b' . $keyword . '\b/ie';
        }

        $tmp = preg_replace($replace, '"<b style=\"color:#0000FF\">".strtoupper("$0")."</b>"', $tmp);

        $tmp = preg_replace('/\b([\.0-9]+)\b/', '<b style="color:#008000">\1</b>', $tmp);

        $tmp = preg_replace('/([\(\)])/', '<b style="color:#FF0000">\1</b>', $tmp);

        if (count($quote_list_text)) {
            $quote_list_text = array_reverse($quote_list_text, true);
            foreach ($quote_list_text as $k=> $i) {
                $tmp = str_replace('<text' . $k . '>', '<span style="color:#777;">' . $i . '</span>', $tmp);
            }
        }

        if (count($quote_list_symbols)) {
            $quote_list_symbols = array_reverse($quote_list_symbols, true);
            foreach ($quote_list_symbols as $k=> $i) {
                $tmp = str_replace('<symbol' . $k . '>', $i, $tmp);
            }
        }

        $sqlHtml = trim($tmp);
        if ($nl2br) {
            $sqlHtml = nl2br($sqlHtml);
        }

        return self::i()->dump($sqlHtml, $sqlName . '::html');
    }

    /**
     * Do the real json encoding adding human readability. Supports automatic indenting with tabs
     * @param array|object $in     The array or object to encode in json
     * @param int          $indent The indentation level. Adds $indent tabs to the string
     * @return string
     */
    protected function _jsonEncode($in, $indent = 0)
    {
        $out = '';

        foreach ($in as $key => $value) {

            $out .= str_repeat("    ", $indent + 1);
            $out .= json_encode((string)$key) . ': ';

            if (is_object($value) || is_array($value)) {
                $out .= $this->_jsonEncode($value, $indent + 1);
            } else {
                $out .= json_encode($value);
            }

            $out .= ",\n";
        }

        if (!empty($out)) {
            $out = substr($out, 0, -2);
        }

        $out = " {\n" . $out;
        $out .= "\n" . str_repeat("    ", $indent) . "}";

        return $out;
    }

}

/**
 * Alias for JBDump::i()->dump($var) with additions params
 * @param   mixed   $var    Variable
 * @param   string  $name   Variable name
 * @param   bool    $isDie  Die after dump
 * @return  JBDump
 */
function JBDump($var = 'JBDump::variable is no set', $isDie = true, $name = '...')
{
    $_this = JBDump::i();

    if ($var != 'JBDump::variable is no set') {

        if ($_this->isDebug()) {
            $_this->dump($var, $name);
            $isDie && die('JBDump_die');
        }

    }

    return $_this;
}
