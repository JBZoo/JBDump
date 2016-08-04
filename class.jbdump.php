<?php
/**
 * Library for dump variables and profiling PHP code
 * The idea and the look was taken from Krumo project
 * PHP version 5.3 or higher
 * *
 * Example:<br/>
 *      jbdump($myLoveVariable);<br/>
 *      jbdump($myLoveVariable, false, 'Var name');<br/>
 *      jbdump::mark('Profiler mark');<br/>
 *      jbdump::log('Message to log file');<br/>
 *      jbdump::i()->dump($myLoveVariable);<br/>
 *      jbdump::i()->post()->get()->mark('Profiler mark');<br/>
 * *
 * Simple include in project on index.php file
 * if (file_exists( dirname(__FILE__) . '/class.jbdump.php')) { require_once dirname(__FILE__) . '/class.jbdump.php'; }
 * *
 * @package     JBDump
 * @copyright   Copyright (c) 2009-2015 JBDump.org
 * @license     http://www.gnu.org/licenses/gpl.html GNU/GPL
 * @author      SmetDenis <admin@JBDump.org>, <admin@jbzoo.com>
 * @link        http://joomla-book.ru/projects/jbdump
 * @link        http://JBDump.org/
 * @link        http://code.google.com/intl/ru-RU/apis/chart/index.html
 */

// Check PHP version
!version_compare(PHP_VERSION, '5.3.10', '=>') or die('Your host needs to use PHP 5.3.10 or higher to run JBDump');

/**
 * Class JBDump
 */
class JBDump
{
    /**
     * Default configurations
     * @var array
     */
    protected static $_config = array
    (
        'root'     => null, // project root directory
        'showArgs' => 0, // show Args in backtrace
        'showCall' => 1,

        // // // file logger
        'log'      => array(
            'path'      => null, // absolute log path
            'file'      => 'jbdump', // log filename
            'format'    => "{DATETIME}\t{CLIENT_IP}\t\t{FILE}\t\t{NAME}\t\t{JBDUMP_MESSAGE}", // fields in log file
            'serialize' => 'print_r', // (none|json|serialize|print_r|var_dump|format|php_array)
        ),

        // // // profiler
        'profiler' => array(
            'auto'       => 1, // Result call automatically on destructor
            'render'     => 20, // Profiler render (bit mask). See constants jbdump::PROFILER_RENDER_*
            'showStart'  => 0, // Set auto mark after jbdump init
            'showEnd'    => 0, // Set auto mark before jbdump destruction
            'showOnAjax' => 0, // Show profiler information on ajax calls
            'traceLimit' => 3, // Limit for function JBDump::incTrace();
        ),

        // // // sorting (ASC)
        'sort'     => array(
            'array'   => 0, // by keys
            'object'  => 1, // by properties name
            'methods' => 1, // by methods name
        ),

        // // // personal dump
        'personal' => array(
            'ip'           => array(), // IP address for which to work debugging
            'requestParam' => 0, // $_REQUEST key for which to work debugging
            'requestValue' => 0, // $_REQUEST value for which to work debugging
        ),

        // // // error handlers
        'errors'   => array(
            'reporting'          => 0, // set error reporting level while construct
            'errorHandler'       => 0, // register own handler for PHP errors
            'errorBacktrace'     => 0, // show backtrace for errors
            'exceptionHandler'   => 0, // register own handler for all exeptions
            'exceptionBacktrace' => 0, // show backtrace for exceptions
            'context'            => 0, // show context for errors
            'logHidden'          => 0, // if error message not show, log it
            'logAll'             => 0, // log all error in syslog
        ),

        // // // mail send
        'mail'     => array(
            'to'      => 'jbdump@example.com', // mail to
            'subject' => 'JBDump debug', // mail subject
            'log'     => 0, // log all mail messages
        ),

        // // // dump config
        'dump'     => array(
            'render'       => 'html', // (lite|log|mail|print_r|var_dump|html)
            'stringLength' => 80, // cutting long string
            'maxDepth'     => 4, // the maximum depth of the dump
            'showMethods'  => 1, // show object methods
            'die'          => 0, // die after dumping variable
            'expandLevel'  => 1, // expand the list to the specified depth
        ),
    );

    /**
     * Flag enable or disable the debugger
     * @var bool
     */
    public static $enabled = true;

    /**
     * Counters of calling
     * @var array
     */
    protected static $_counters = array(
        'mode_0' => array(),
        'mode_1' => array(),
        'trace'  => array(),
    );

    /**
     * Counteins of pairs calling
     * @var array
     */
    protected static $_profilerPairs = array();

    /**
     * Library version
     * @var string
     */
    const VERSION = '1.5.1';

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
    protected $_site = 'http://jbdump.org/';

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
    protected $_logPath = null;

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
     * Fix bug anti cycling destructor
     * @var bool
     */
    protected static $_isDie = false;

    /**
     * Constructor, set internal variables and self configuration
     * @param array $options Initialization parameters
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
            'time'       => 0,
            'timeDiff'   => 0,
            'memory'     => self::_getMemory(),
            'memoryDiff' => 0,
            'label'      => 'jbdump::init',
            'trace'      => '',
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

        if (!self::$_config['profiler']['showOnAjax'] && self::isAjax()) {
            return;
        }

        // JBDump incriment output
        if (!empty(self::$_counters['mode_0'])) {

            arsort(self::$_counters['mode_0']);

            foreach (self::$_counters['mode_0'] as $counterName => $count) {
                echo '<pre>JBDump Increment / "' . $counterName . '" = ' . $count . '</pre>';
            }
        }

        // JBDump trace incriment output
        if (!empty(self::$_counters['trace'])) {

            uasort(self::$_counters['trace'], function ($a, $b) {
                if ($a['count'] == $b['count']) {
                    return 0;
                }
                return ($a['count'] < $b['count']) ? 1 : -1;
            });

            foreach (self::$_counters['trace'] as $counterHash => $traceInfo) {
                self::i()->dump($traceInfo['trace'], $traceInfo['label'] . ' = ' . $traceInfo['count']);
            }
        }

        // JBDump pairs profiler
        if (!empty(self::$_profilerPairs)) {

            foreach (self::$_profilerPairs as $label => $pairs) {

                $timeDelta = $memDelta = $count = 0;
                $memDiffs  = $timeDiffs = array();

                foreach ($pairs as $key => $pair) {

                    if (!isset($pair['stop']) || !isset($pair['start'])) {
                        continue;
                    }

                    $count++;

                    $tD = $pair['stop'][0] - $pair['start'][0];
                    $mD = $pair['stop'][1] - $pair['start'][1];

                    $timeDiffs[] = $tD;
                    $memDiffs[]  = $mD;

                    $timeDelta += $tD;
                    $memDelta += $mD;
                }

                if ($count > 0) {

                    $timeAvg = array_sum($timeDiffs) / $count;
                    $memoAvg = array_sum($memDiffs) / $count;

                    $timeStd = $memoStd = '';
                    if ($count > 1) {
                        $timeStdValue = $this->_stdDev($timeDiffs);
                        $memoStdValue = $this->_stdDev($memDiffs);

                        $timeStd = ' <span title="' . round(($timeStdValue / $timeAvg) * 100) . '%">(&plusmn;'
                            . self::_profilerFormatTime($timeStdValue, true, 2) . ')</span>';
                        $memoStd = ' <span title="' . round(($memoStdValue / $memoAvg) * 100) . '%">(&plusmn;'
                            . self::_profilerFormatMemory($memoStdValue, true) . ')</span>';
                    }

                    $output = array(
                        '<pre>JBDump ProfilerPairs / "' . $label . '"',
                        'Count  = ' . $count,
                        'Time   = ' . implode(";\t\t", array(
                            'ave: ' . self::_profilerFormatTime($timeAvg, true, 2) . $timeStd,
                            'sum: ' . self::_profilerFormatTime(array_sum($timeDiffs), true, 2),
                            'min(' . (array_search(min($timeDiffs), $timeDiffs) + 1) . '):' . self::_profilerFormatTime(min($timeDiffs), true, 2),
                            'max(' . (array_search(max($timeDiffs), $timeDiffs) + 1) . '): ' . self::_profilerFormatTime(max($timeDiffs), true, 2),
                        )),
                        'Memory = ' . implode(";\t\t", array(
                            'ave: ' . self::_profilerFormatMemory($memoAvg, true) . $memoStd,
                            'sum: ' . self::_profilerFormatMemory(array_sum($memDiffs), true),
                            'min(' . (array_search(min($memDiffs), $memDiffs) + 1) . '): ' . self::_profilerFormatMemory(min($memDiffs), true),
                            'max(' . (array_search(max($memDiffs), $memDiffs) + 1) . '): ' . self::_profilerFormatMemory(max($memDiffs), true),
                        )),
                        '</pre>'
                    );
                } else {
                    $output = array(
                        '<pre>JBDump ProfilerPairs / "' . $label . '"',
                        'Count  = ' . $count,
                        '</pre>'
                    );
                }

                echo implode(PHP_EOL, $output);
            }
        }
    }

    /**
     * Returns the global JBDump object, only creating it
     * if it doesn't already exist
     * @param   array $options Initialization parameters
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
     * Include css and js files in document
     * @param bool $force
     * @return void
     */
    protected function _initAssets($force = true)
    {
        static $loaded;
        if (!isset($loaded) || $force) {
            $loaded = true;

            echo
            '<script type="text/javascript">
                function jbdump() {}
                jbdump.reclass = function (el, className) {if (el.className.indexOf(className) < 0) {el.className += " " + className;}};
                jbdump.unclass = function (el, className) {if (el.className.indexOf(className) > -1) {el.className = el.className.replace(" " + className, "");}};
                jbdump.toggle = function (el) {var ul = el.parentNode.getElementsByTagName("ul");for (var i = 0; i < ul.length; i++) {
                if (ul[i].parentNode.parentNode == el.parentNode) {ul[i].parentNode.style.display = ul[i].parentNode.style.display == "none" ? "block" : "none";}}
                if (ul[0].parentNode.style.display == "block") {jbdump.reclass(el, "jbopened");} else {jbdump.unclass(el, "jbopened");}};
            </script>
            <style>
                #jbdump{border:solid 1px #333;border-radius:6px;position:relative;z-index:10101;min-width:400px;max-width:1280px;margin:6px auto;padding:6px;clear:both;background:#fff;opacity:1;filter:alpha(opacity=100);font-size:12px !important;line-height:16px !important;text-align:left!important;}
                #jbdump ::selection {background: #89cac9;color: #333;text-shadow: none;}
                #jbdump *{opacity:1;filter:alpha(opacity=100);font-size:12px !important;line-height:16px!important;font-family:monospace, Verdana, Helvetica;margin:0;padding:0;color:#333;}
                #jbdump li{list-style:none !important;}
                #jbdump .jbnode{margin: 0;padding: 0;}
                #jbdump .jbchild{margin: 0;padding: 0;}
                #jbdump .jbnode .jbnode{margin-left:20px;}
                #jbdump .jbnode .jbpreview{font-family:"Courier New";font-size:12px!important;overflow-wrap:normal;flex-direction:row;display:block;word-wrap:normal;white-space:pre;background:#f9f9b5;border:solid 1px #808000;border-radius:6px;overflow:auto;margin:12px 0;padding:6px;min-height:58px;height:300px;text-align:left !important;width:97%;color:#333;min-width:300px;}
                #jbdump .jbnode .jbpreview * {font-family:"Courier New";font-size:12px!important;}
                #jbdump .jbchild{overflow:hidden;}
                #jbdump .jbvalue{font-weight:bold;font-family:monospace, Verdana, Helvetica;font-size:12px;}
                #jbdump .jbfooter{border-top:1px dotted #ccc;padding-top:4px;}
                #jbdump .jbfooter .jbversion{float:right;}
                #jbdump .jbfooter .jbversion a{color:#ddd;font-size:10px !important;text-decoration:none;}
                #jbdump .jbfooter .jbversion a:hover{color:#333;text-decoration:underline;}
                #jbdump .jbfooter .jbpath{font-family:"Courier New";}
                #jbdump .jbelement{padding:3px 3px 3px 20px;background-repeat:no-repeat;background-color:#fff;background-position:5px 6px;background-image:url(\'data:image/gif;base64,R0lGODlhCQAJALMAAP////8AAICAgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAJAAkAAAQSEAAhq6VWUpx3n+AVVl42ilkEADs=\');}
                #jbdump .jbelement:hover{background-color:#c6e5ff;}
                #jbdump .jbelement.jbexpand{background-image:url(\'data:image/gif;base64,R0lGODlhCQAJALMAAP///wAAAP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAJAAkAAAQTEIAna33USpwt79vncRpZgpcGRAA7\');cursor:pointer;}
                #jbdump .jbelement.jbexpand.jbopened{background-image:url(\'data:image/gif;base64,R0lGODlhCQAJALMAAP///wAAAP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAAAAAJAAkAAAQQEMhJ63w4Z6C37JUXWmQJRAA7\');}
                #jbdump .jbelement .jbname{color:#a00;font-weight:bold;}
                #jbdump .jbelement .jbtype-integer{color:#00d;}
                #jbdump .jbelement .jbtype-float{color:#099;}
                #jbdump .jbelement .jbtype-boolean{color:#990;}
                #jbdump .jbelement .jbtype-string{color:#090;}
                #jbdump .jbelement .jbtype-array{color:#990;}
                #jbdump .jbelement .jbtype-null{color:#999;}
                #jbdump .jbelement .jbtype-max-depth{color:#900;}
                #jbdump .jbelement .jbtype-object{color:#c0c;}
                #jbdump .jbelement .jbtype-closure{color:#c0c;}
                #jbdump_profile_chart_table td img{height:12px !important;}
                #jbdump_profile_chart_table{color:#333 !important;}
                .google-visualization-table-table td img{height:12px !important;}
            </style>';
        }
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
     * @param $reportLevel error_reporting level
     * @return bool
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
     * @param   integer $time Time limit in seconds
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
     * @return JBDump
     */
    public static function off()
    {
        self::$enabled = false;
        return self::i();
    }

    /**
     * Set debug parameters
     * @param array  $data Params for debug, see self::$_config vars
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
            $this->_logPath = $data['log']['path'];

        } elseif (!self::$_config['log']['path'] || !$this->_logPath) {
            $this->_logPath = dirname(__FILE__) . self::DS . 'logs';
        }

        // set log filename
        $logFile = 'jbdump';
        if (isset($data['log']['file']) && $data['log']['file']) {
            $logFile = $data['log']['file'];

        } elseif (!self::$_config['log']['file'] || !$this->_logfile) {
            $logFile = 'jbdump';
        }

        $this->_logfile = $this->_logPath . self::DS . $logFile . '_' . date('Y.m.d') . '.log.php';

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
     * @param   mixed  $entry    Text to log file
     * @param   string $markName Name of log record
     * @param   array  $params   Additional params
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
        } elseif (is_resource($entry)) {
            $entry = 'resource of "' . get_resource_type($entry) . '"';
        }

        // serialize type
        if (self::$_config['log']['serialize'] == 'formats') {
            // don't change log entry

        } elseif (self::$_config['log']['serialize'] == 'none') {
            $entry = array('jbdump_message' => $entry);

        } elseif (self::$_config['log']['serialize'] == 'json') {
            $entry = array('jbdump_message' => @json_encode($entry));

        } elseif (self::$_config['log']['serialize'] == 'serialize') {
            $entry = array('jbdump_message' => serialize($entry));

        } elseif (self::$_config['log']['serialize'] == 'print_r') {
            $entry = array('jbdump_message' => print_r($entry, true));

        } elseif (self::$_config['log']['serialize'] == 'php_array') {
            $markName = (empty($markName) || $markName == '...') ? 'dumpVar' : $markName;
            $entry    = array('jbdump_message' => JBDump_array2php::toString($entry, $markName));

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
            error_log($line . PHP_EOL, 3, $_this->_logfile);
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

            if (!is_dir($this->_logPath) && $this->_logPath) {
                mkdir($this->_logPath, 0777, true);
            }

            $header[] = "#<?php die('Direct Access To Log Files Not Permitted'); ?>";
            $header[] = "#Date: " . date(DATE_RFC822, time());
            $header[] = "#Software: JBDump v" . self::VERSION . ' by Joomla-book.ru';
            $fields   = str_replace("{", "", self::$_config['log']['format']);
            $fields   = str_replace("}", "", $fields);
            $fields   = strtolower($fields);
            $header[] = '#' . str_replace("\t", "\t", $fields);

            $head = implode(PHP_EOL, $header);
        } else {
            $head = false;
        }

        if ($head) {
            error_log($head . PHP_EOL, 3, $this->_logfile);
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
    public static function memory($formated = true)
    {
        if (!self::isDebug()) {
            return false;
        }

        $memory = self::i()->_getMemory();
        if ($formated) {
            $memory = self::i()->_formatSize($memory);
        }

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
     * @param   string $url     URL string
     * @param   string $varname URL name
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
     * @param   bool $showInternal Get only internal functions
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
     * @param   bool $zend Get only Zend extensions
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
     * @param   string $extension Extension name
     * @param   bool   $details   Retrieve details settings or only the current value for each setting
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
     * Convert JSON format to human readability
     * @param        $json
     * @param string $name
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
     * @param string $url
     * @param array  $data
     * @param string $method
     * @param array  $params
     * @return JBDump
     */
    public static function loadUrl($url, $data = array(), $method = 'get', $params = array())
    {
        $result = array(
            'lib'     => '',
            'code'    => 0,
            'headers' => array(),
            'body'    => null,
            'error'   => null,
            'info'    => null,
        );

        $method    = trim(strtolower($method));
        $queryData = http_build_query((array)$data, null, '&');
        if ($method == 'get') {
            $url = $url . (strpos($url, '?') === false ? '?' : '&') . $queryData;
        }

        if (function_exists('curl_init') && is_callable('curl_init')) {
            $result['lib'] = 'cUrl';

            $options = array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,     // return web page
                CURLOPT_HEADER         => true,     // return headers
                CURLOPT_ENCODING       => "",       // handle all encodings
                CURLOPT_USERAGENT      => "JBDump", // who am i
                CURLOPT_AUTOREFERER    => true,     // set referer on redirect
                CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
                CURLOPT_TIMEOUT        => 120,      // timeout on response
                CURLOPT_MAXREDIRS      => 20,       // stop after 10 redirects

                // Disabled SSL Cert checks
                CURLOPT_SSL_VERIFYPEER => isset($params['ssl']) ? $params['ssl'] : true,

                CURLOPT_HTTPHEADER     => array(
                    'Expect:', // http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
                    'Content-Type:application/x-www-form-urlencoded; charset=utf-8',
                ),
            );
            if (isset($params['cert'])) {
                $options[CURLOPT_CAINFO] = __DIR__ . '/jbdump.pem';
            }

            if (!ini_get('safe_mode') && !ini_get('open_basedir')) {
                $options[CURLOPT_FOLLOWLOCATION] = true;
            }

            if ($method == 'post') {
                $options[CURLOPT_POSTFIELDS] = $queryData;
                $options[CURLOPT_POST]       = true;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            $result['full'] = curl_exec($ch);

            if (curl_errno($ch) || curl_error($ch)) {
                $result['error'] = '#' . curl_errno($ch) . ' - "' . curl_error($ch) . '"';
            }

            $info = curl_getinfo($ch);
            curl_close($ch);

            // parse response
            $redirects      = isset($info['redirect_count']) ? $info['redirect_count'] : 0;
            $response       = explode("\r\n\r\n", $result['full'], 2 + $redirects);
            $result['body'] = array_pop($response);
            $headers        = explode("\r\n", array_pop($response));
            // code
            preg_match('/[0-9]{3}/', array_shift($headers), $matches);
            $result['code'] = count($matches) ? $matches[0] : null;

            // parse headers
            $resHeaders = array();
            foreach ($headers as $header) {
                $pos   = strpos($header, ':');
                $name  = trim(substr($header, 0, $pos));
                $value = trim(substr($header, ($pos + 1)));

                $resHeaders[$name] = $value;
            }

            $result['info']    = $info;
            $result['headers'] = $resHeaders;

        } else {
            $result['lib'] = 'file_get_contents';

            $context = null;
            if ($method == 'post') {
                $context = stream_context_create(array('http' => array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $queryData
                )));
            }

            $result['full'] = file_get_contents($url, false, $context);
        }

        return self::i()->dump($result, 'Load URL');
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
        $locale = explode(PHP_EOL, trim(ob_get_contents()));
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
     * @param mixed  $var     The variable to dump
     * @param string $varname Label to prepend to output
     * @param array  $params  Echo output if true
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
     * Render variable as phpArray
     * @param mixed  $var
     * @param string $name
     * @param bool   $isReturn
     * @return mixed
     */
    public static function phpArray($var, $varname = 'varName', $isReturn = false)
    {
        if (!self::isDebug()) {
            return false;
        }

        $output = JBDump_array2php::toString($var, $varname);
        if ($isReturn) {
            return $output;
        }

        $_this = self::i();
        $_this->_dumpRenderHtml($output, $varname, $params);

        return $_this;
    }

    /**
     * Wrapper for PHP var_dump function
     * @param   mixed  $var     The variable to dump
     * @param   string $varname Echo output if true
     * @param   array  $params  Additionls params
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
        $_this  = self::i();

        // neaten the newlines and indents
        $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
        //if (!extension_loaded('xdebug')) {
        $output = $_this->_htmlChars($output);
        //}

        $_this->_dumpRenderHtml($output, $varname . '::html', $params);

        return $_this;
    }

    /**
     * Get system backtrace in formated view
     * @param   bool $trace     Custom php backtrace
     * @param   bool $addObject Show objects in result
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
     * @param   string|object $data Object or class name
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
     * @param   string $extensionName Extension name
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
     * @param   string $file path to file
     * @return  JBDump
     */
    public static function pathInfo($file)
    {
        $result = self::_pathInfo($file);
        return self::i()->dump($result, '! pathInfo (' . $file . ') !');
    }

    /**
     * Dump all info about function
     * @param   string|Closure $functionName Closure or function name
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
     * @param string $label
     * @return bool
     * @return  JBDump
     */
    public static function markStart($label = 'default')
    {
        $time   = self::_microtime();
        $memory = self::_getMemory();

        $_this = self::i();
        if (!$_this->isDebug()) {
            return false;
        }

        if (!isset(self::$_profilerPairs[$label])) {
            self::$_profilerPairs[$label] = array();
        }

        $length = count(self::$_profilerPairs[$label]);
        if (isset(self::$_profilerPairs[$label][$length]['start'])) {
            $length++;
        }

        self::$_profilerPairs[$label][$length] = array('start' => array($time, $memory));

        return $_this;
    }

    /**
     * @param int    $outputMode
     *      0 - on destructor (PHP Die)
     *      1 - immediately
     * @param string $name
     */
    public static function inc($name = null, $outputMode = 0)
    {
        $_this = self::i();
        if (!$_this->isDebug()) {
            return false;
        }

        if (!$name) {
            $trace     = debug_backtrace();
            $traceInfo = $_this->_getOneTrace($trace[1]);
            $line      = isset($trace[0]['line']) ? $trace[0]['line'] : 0;
            $name      = $traceInfo['func'] . ', line #' . $line;
        }

        if (is_string($outputMode)) {
            $name       = $outputMode;
            $outputMode = 0;
        }

        if (!isset(self::$_counters['mode_' . $outputMode][$name])) {
            self::$_counters['mode_' . $outputMode][$name] = 0;
        }

        self::$_counters['mode_' . $outputMode][$name]++;

        if ($outputMode == 1) {
            echo '<pre>' . $name . ' = ' . self::$_counters['mode_' . $outputMode][$name] . '</pre>';
        }

        return self::$_counters['mode_' . $outputMode][$name];
    }

    /**
     * @param string $label
     * @return bool
     * @return  int
     */
    public static function incTrace($label = null)
    {
        $_this = self::i();
        if (!$_this->isDebug()) {
            return false;
        }

        $trace = debug_backtrace();

        if (!$label) {
            $traceInfo = $_this->_getOneTrace($trace[1]);
            $line      = isset($trace[0]['line']) ? $trace[0]['line'] : 0;
            $label     = $traceInfo['func'] . ', line #' . $line;
        }

        unset($trace[0]);
        unset($trace[1]);
        $trace     = array_slice($trace, 0, self::$_config['profiler']['traceLimit']);
        $traceInfo = array();
        foreach ($trace as $oneTrace) {
            $traceData   = $_this->_getOneTrace($oneTrace);
            $line        = isset($oneTrace['line']) ? $oneTrace['line'] : 0;
            $traceInfo[] = $traceData['func'] . ', line #' . $line;
        }

        $hash = md5(serialize($traceInfo));

        if (!isset(self::$_counters['trace'][$hash])) {

            self::$_counters['trace'][$hash] = array(
                'count' => 0,
                'label' => $label,
                'trace' => $traceInfo,
            );
        }

        self::$_counters['trace'][$hash]['count']++;

        return self::$_counters['trace'][$hash]['count'];
    }

    /**
     * @param string $label
     * @return  JBDump
     */
    public static function markStop($label = 'default')
    {
        $time   = self::_microtime();
        $memory = self::_getMemory();

        $_this = self::i();
        if (!$_this->isDebug()) {
            return false;
        }

        if (!isset(self::$_profilerPairs[$label])) {
            self::$_profilerPairs[$label] = array();
        }

        $length = count(self::$_profilerPairs[$label]);
        if ($length > 0) {
            $length--;
        }

        self::$_profilerPairs[$label][$length]['stop'] = array($time, $memory);

        return $_this;
    }

    /**
     * Output a time mark
     * The mark is returned as text current profiler status
     * @param   string $label A label for the time mark
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
     * @param   int $mode Render mode
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
     * @param string $data
     * @return string
     */
    protected function _htmlChars($data)
    {
        /*
         * // experimental
        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($data);
            if ($encoding == 'ASCII') {
                $encoding = 'cp1251';
            }
        }
        */

        $encoding = 'UTF-8';
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            $flags = ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE;
        } else {
            $flags = ENT_QUOTES;
        }

        $data = (string)$data;
        // $data = iconv('WINDOWS-1251', 'UTF-8//TRANSLIT', $data);

        return htmlspecialchars($data, $flags, $encoding, true);
    }

    /**
     * Convert profiler memory value to usability view
     * @param int  $memoryBytes
     * @param bool $addMeasure
     * @return float
     */
    protected static function _profilerFormatMemory($memoryBytes, $addMeasure = false)
    {
        $bytes = round($memoryBytes / 1024 / 1024, 3);

        if ($addMeasure) {
            $bytes .= ' MB';
        }

        return $bytes;
    }

    /**
     * Convert profiler time value to usability view
     * @param      $time
     * @param bool $addMeasure
     * @return float
     */
    protected static function _profilerFormatTime($time, $addMeasure = false, $round = 0)
    {
        $time = round($time * 1000, $round);

        if ($addMeasure) {
            $time .= ' ms';
        }

        return $time;
    }

    /**
     * Convert profiler mark to string
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
            $totalInfo = PHP_EOL . "\t" . implode(PHP_EOL . "\t", $totalInfo) . PHP_EOL;
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
        $output = PHP_EOL;
        foreach ($this->_bufferInfo as $key => $mark) {
            $output .= "\t" . self::_profilerFormatMark($mark) . PHP_EOL;
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
            google.load('visualization', '1', {packages: ['table']});
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
                foreach ($this->_bufferInfo as $key=> $mark) : ?>
                data.setCell(<?php echo $key;?>, 0, <?php echo ++$i;?>);
                data.setCell(<?php echo $key;?>, 1, '<?php echo $mark['label'];?>');
                data.setCell(<?php echo $key;?>, 2, '<?php echo $mark['trace'];?>');
                data.setCell(<?php echo $key;?>, 3, <?php echo self::_profilerFormatTime($mark['time']);?>);
                data.setCell(<?php echo $key;?>, 4, <?php echo self::_profilerFormatTime($mark['timeDiff']);?>);
                data.setCell(<?php echo $key;?>, 5, <?php echo self::_profilerFormatMemory($mark['memory']);?>);
                data.setCell(<?php echo $key;?>, 6, "<?php echo self::_profilerFormatMemory($mark['memoryDiff']);?>");
                <?php endforeach; ?>

                var formatter = new google.visualization.TableBarFormat({width: 120});
                formatter.format(data, 4);
                formatter.format(data, 6);

                var table = new google.visualization.Table(document.getElementById('jbdump_profile_chart_table'));
                table.draw(data, {
                    allowHtml    : true,
                    showRowNumber: false
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
            google.load("visualization", "1", {packages: ["corechart"]});
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
                    'width' : 750,
                    'height': 400,
                    'title' : 'JBDump profiler by time'
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
                    'width' : 750,
                    'height': 400,
                    'title' : 'JBDump profiler by memory'
                });
            });
        </script>
    <?php
    }

    /**
     * Dumper variable
     * @param   mixed  $data    Mixed data for dump
     * @param   string $varname Variable name
     * @param   array  $params  Additional params
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
        <div id="jbdump">
            <ul class="jbnode">
                <?php $this->_dump($data, $varname); ?>
                <li class="jbfooter">
                    <div class="jbversion">
                        <a href="<?php echo $this->_site; ?>" target="_blank">JBDump v<?php echo self::VERSION; ?></a>
                    </div>
                    <?php if (self::$_config['showCall']) : ?>
                        <div class="jbpath"><?php echo $text . ' ' . $path; ?></div>
                    <?php endif; ?>
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

        if (is_string($data) && strlen($data) == 0) {
            $printrOut = '""';
        } else {
            $printrOut = print_r($data, true);
        }

        if (!self::isCli()) {
            $printrOut = $this->_htmlChars($printrOut);
        }

        if (self::isAjax()) {
            $printrOut = str_replace('] =&gt;', '] =>', $printrOut);
        }

        $output   = array();
        if (!self::isCli()) {
            $output[] = '<pre>------------------------------' . PHP_EOL;
        }

        $output[] = $varname . ' = ';
        $output[] = rtrim($printrOut, PHP_EOL);

        if (!self::isCli()) {
            $output[] = PHP_EOL . '------------------------------</pre>' . PHP_EOL;
        } else {
            $output[] = PHP_EOL;
        }

        if (!self::isAjax()) {
            echo '<pre class="jbdump" style="text-align: left;">' . implode('', $output) . '</pre>' . PHP_EOL;
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
        $this->mail(array(
            'varname' => $varname,
            'data'    => $data
        ));
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
     * @param   string $data Data from get hash
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
     * @return float
     */
    public static function _microtime()
    {
        return microtime(true);
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
     * @param   mixed  $data Mixed data for dump
     * @param   string $name Variable name
     * @return  JBDump
     */
    protected function _dump($data, $name = '...')
    {
        $varType = strtolower(getType($data));

        $advType = false;
        if ($varType == 'string' && preg_match('#(.*)::(.*)#', $name, $matches)) {
            $matches[2] = trim(strToLower($matches[2]));
            if ($this->_strlen($matches[2]) > 0) {
                $advType = $matches[2];
            }
            $name = $matches[1];
        }

        if ($this->_strlen($name) > 80) {
            $name = substr($name, 0, 80) . '...';
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
        <div class="jbnest" style="<?php echo $isExpanded ? 'display:block' : 'display:none'; ?>">
            <ul class="jbnode">
                <?php
                $keys = ($_is_object) ? array_keys(@get_object_vars($data)) : array_keys($data);

                // sorting
                if (self::$_config['sort']['object'] && $_is_object) {
                    sort($keys);
                } elseif (self::$_config['sort']['array']) {
                    sort($keys);
                }

                // get entries
                foreach ($keys as $key) {
                    $value = null;
                    if ($_is_object && property_exists($data, $key)) {
                        $value = $data->$key;
                    } else {
                        if (is_array($data) && array_key_exists($key, $data)) {
                            $value = $data[$key];
                        }
                    }

                    $this->_dump($value, $key);
                }

                // get methods
                if ($_is_object && self::$_config['dump']['showMethods']) {
                    if ($methods = $this->_getMethods($data)) {
                        $this->_dump($methods, '&lt;! methods of "' . get_class($data) . '" !&gt;');
                    }
                }
                ?>
            </ul>
        </div>
    <?php
    }

    /**
     * Render HTML for NULL type
     * @param   string $name Variable name
     * @return  void
     */
    protected function _null($name)
    {
        ?>
        <li class="jbchild">
            <div class="jbelement">
                <span class="jbname"><?php echo $name; ?></span>
                (<span class="jbtype jbtype-null">NULL</span>)
            </div>
        </li>
    <?php
    }

    /**
     * Render HTML for Boolean type
     * @param   bool   $data Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _boolean($data, $name)
    {
        $data = $data ? 'TRUE' : 'FALSE';
        $this->_renderNode('Boolean', $name, '<span style="color:00e;">' . $data . '</span>');
    }

    /**
     * Render HTML for Integer type
     * @param   integer $data Variable
     * @param   string  $name Variable name
     * @return  void
     */
    protected function _integer($data, $name)
    {
        $this->_renderNode('Integer', $name, (int)$data);
    }

    /**
     * Render HTML for float (double) type
     * @param   float  $data Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _float($data, $name)
    {
        $this->_renderNode('Float', $name, (float)$data);
    }

    /**
     * Render HTML for resource type
     * @param   resource $data Variable
     * @param   string   $name Variable name
     * @return  void
     */
    protected function _resource($data, $name)
    {
        $data = get_resource_type($data);
        $this->_renderNode('Resource', $name, $data);
    }

    /**
     * Get valid string length
     * @param   string $string Some string
     * @return  int
     */
    protected function _strlen($string)
    {
        $encoding = function_exists('mb_detect_encoding') ? mb_detect_encoding($string) : false;
        return $encoding ? mb_strlen($string, $encoding) : strlen($string);
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
        $dataLength = $this->_strlen($data);

        $_extra = false;
        if ($advType == 'html') {
            $_extra = true;
            $_      = 'HTML Code';

            $data = '<pre class="jbpreview">' . $data . '</pre>';

        } elseif ($advType == 'source') {

            $data = trim($data);
            if ($data && strpos($data, '<?') !== 0) {
                $_      = 'PHP Code';
                $_extra = true;
                $data   = "<?php" . PHP_EOL . PHP_EOL . $data;
                $data   = '<pre class="jbpreview">' . highlight_string($data, true) . '</pre>';
            } else {
                $_    = '// code not found';
                $data = null;
            }

        } else {
            $_ = $data;

            if (!(
                strpos($data, "\r") === false &&
                strpos($data, "\n") === false &&
                strpos($data, "  ") === false &&
                strpos($data, "\t") === false
            )
            ) {
                $_extra = true;
            } else {
                $_extra = false;
            }

            if ($this->_strlen($data)) {
                if ($this->_strlen($data) > self::$_config['dump']['stringLength']) {
                    if (function_exists('mb_substr')) {
                        $_ = mb_substr($data, 0, self::$_config['dump']['stringLength'] - 3) . '...';
                    } else {
                        $_ = substr($data, 0, self::$_config['dump']['stringLength'] - 3) . '...';
                    }

                    $_extra = true;
                }

                $_    = $this->_htmlChars($_);
                $data = '<textarea readonly="readonly" class="jbpreview">' . $this->_htmlChars($data) . '</textarea>';
            }
        }
        ?>
        <li class="jbchild">
            <div
                class="jbelement <?php echo $_extra ? ' jbexpand' : ''; ?>" <?php if ($_extra) { ?> onClick="jbdump.toggle(this);"<?php } ?>>
                <span class="jbname"><?php echo $name; ?></span>
                (<span class="jbtype jbtype-string">String</span>, <?php echo $dataLength; ?>)
                <span class="jbvalue"><?php echo $_; ?></span>
            </div>
            <?php if ($_extra) { ?>
                <div class="jbnest" style="display:none;">
                    <ul class="jbnode">
                        <li class="jbchild"><?php echo $data; ?></li>
                    </ul>
                </div>
            <?php } ?>
        </li>
    <?php

    }

    /**
     * Render HTML for array type
     * @param   array  $data Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _array(array $data, $name)
    {
        $isExpanded = $this->_isExpandedLevel();

        if (0 === strpos($name, '&lt;! methods of "')) {
            $isExpanded = false;
        }
        
        ?>
        <li class="jbchild">
            <div
                class="jbelement<?php echo count($data) > 0 ? ' jbexpand' : ''; ?> <?= $isExpanded ? 'jbopened' : ''; ?>"
                <?php if (count($data) > 0) { ?> onClick="jbdump.toggle(this);"<?php } ?>>
                <span class="jbname"><?php echo $name; ?></span> (<span
                    class="jbtype jbtype-array">Array</span>, <?php echo count($data); ?>)
            </div>
            <?php if (count($data)) {
                $this->_vars($data, $isExpanded);
            } ?>
        </li>
    <?php
    }

    /**
     * Render HTML for object type
     * @param   object $data Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _object($data, $name)
    {
        
        static $objectIdList = array();
        
        $objectId = spl_object_hash($data);
        if (!isset($objectIdList[$objectId])) {
            $objectIdList[$objectId] = count($objectIdList);
        }
        
        $count      = count(@get_object_vars($data));
        $isExpand   = $count > 0 || self::$_config['dump']['showMethods'];
        $isExpanded = $this->_isExpandedLevel();

        ?>
        <li class="jbchild">
        <div class="jbelement<?php echo $isExpand ? ' jbexpand' : ''; ?> <?= $isExpanded ? 'jbopened' : ''; ?>"
            <?php if ($isExpand) { ?> onClick="jbdump.toggle(this);"<?php } ?>>
            <span class="jbname" title="splHash=<?php echo $objectId;?>"><?php echo $name; ?></span>
            (
                <span class="jbtype jbtype-object"><?php echo get_class($data); ?></span>
                <span style="text-decoration: underline;" title="splHash=<?php echo $objectId;?>">id:<?php echo $objectIdList[$objectId];?></span>,
                l:<?php echo $count; ?>
            )
        </div>
        <?php if ($isExpand) {
        $this->_vars($data, $isExpanded);
    } ?>
    <?php
    }

    /**
     * Render HTML for closure type
     * @param   object $data Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _closure($data, $name)
    {
        $isExpanded = $this->_isExpandedLevel();

        ?>
        <li class="jbchild">
            <div
                class="jbelement<?php echo count($data) > 0 ? ' jbexpand' : ''; ?> <?= $isExpanded ? 'jbopened' : ''; ?>"
                <?php if (count($data) > 0) { ?> onClick="jbdump.toggle(this);"<?php } ?>>
                <span class="jbname"><?php echo $name; ?></span> (<span class="jbtype jbtype-closure">Closure</span>)
                <span class="jbvalue"><?php echo get_class($data); ?></span>
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
     * @param   mixed  $var  Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _undefined($var, $name)
    {
        $this->_renderNode('undefined', $name, '(<span style="color:red">!</span>) getType = ' . gettype($var));
    }

    /**
     * Render HTML for undefined variable
     * @param   string $type Variable type
     * @param   mixed  $data Variable
     * @param   string $name Variable name
     * @return  void
     */
    protected function _renderNode($type, $name, $data)
    {
        $typeAlias = str_replace(' ', '-', strtolower($type));
        ?>
        <li class="jbchild">
            <div class="jbelement">
                <span class="jbname"><?php echo $name; ?></span>
                (<span class="jbtype jbtype-<?php echo $typeAlias; ?>"><?php echo $type; ?></span>)
                <span class="jbvalue"><?php echo $data; ?></span>
            </div>
        </li>
    <?php
    }

    /**
     * Get the IP number of differnt ways
     * @param bool $getSource
     * @return string
     */
    public static function getClientIP($getSource = false)
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     = $_SERVER['HTTP_CLIENT_IP'];
            $source = 'HTTP_CLIENT_IP';

        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip     = $_SERVER['HTTP_X_REAL_IP'];
            $source = 'HTTP_X_REAL_IP';

        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip     = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $source = 'HTTP_X_FORWARDED_FOR';

        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip     = $_SERVER['REMOTE_ADDR'];
            $source = 'REMOTE_ADDR';

        } else {
            $ip     = '0.0.0.0';
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
     * @param   string $path Absolute filepath
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
     * @param   array $info      One trace element
     * @param   bool  $addObject Add object to result (low perfomance)
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
                    . ' ' . $info['function'] . '()';

            } else {
                $_tmp['func'] = $info['function'] . '()';
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
     * @param   integer $bytes Count bytes
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
     * Get last function name and it params from backtrace
     * @param   array $trace Backtrace
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
     * @param   array $trace    Backtrace
     * @param   bool  $fileOnly Show filename only
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
     * @param   array $trace Backtrace
     * @return  array
     */
    protected function _getLastTrace($trace)
    {
        // current filename info
        $curFile       = pathinfo(__FILE__, PATHINFO_BASENAME);
        $curFileLength = $this->_strlen($curFile);

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
     * @param   object $object Backtrace
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
     * @param   string|object $data Object or class name
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

                $result['properties'][$visible][$propertyName]['comment'] = $property->getDocComment();
                $result['properties'][$visible][$propertyName]['static']  = $property->isStatic();
                $result['properties'][$visible][$propertyName]['default'] = $property->isDefault();
                $result['properties'][$visible][$propertyName]['class']   = $property->class;
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
            $optional  = $param->isOptional();
            $paramName = (!$optional ? '*' : '') . $param->name;

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
     * @param string|function $extensionName Function or function name
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
     * @param   string $path
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
     * Convert trace information to readable
     * @param array $trace Standard debug backtrace data
     * @param bool  $addObject
     * @return array
     */
    public function convertTrace($trace, $addObject = false)
    {
        $result = array();
        if (is_array($trace)) {
            foreach ($trace as $key => $info) {
                $oneTrace = self::i()->_getOneTrace($info, $addObject);

                $file = 'undefined';
                if (isset($oneTrace['file'])) {
                    $file = $oneTrace['file'];
                }

                $result['#' . ($key - 1) . ' ' . $oneTrace['func']] = $file;
            }
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
                $errorMessage = date(self::DATE_FORMAT, time()) . ' ' . $errorMessage . PHP_EOL;

                $logPath = self::$_config['log']['path']
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
            $desc = '<b style="color:red;">*</b> ' . $errType[$errNo] . ' / ' . $this->_htmlChars($result['message']);
            $this->dump($result, $desc);
        }

        return true;
    }

    /**
     * Exception handler
     * @param   Exception $exception PHP exception object
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
            $this->_dumpRenderLite(PHP_EOL . $result['string'], '** EXCEPTION / ' . $this->_htmlChars($result['message']));

        } else {
            $this->_initAssets(true);
            $this->dump($result, '<b style="color:red;">**</b> EXCEPTION / ' . $this->_htmlChars($result['message']));
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
     * @return  bool
     */
    public static function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            return true;

        } elseif (self::isCli()) {
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
     * Check invocation of PHP is from the command line (CLI)
     * @return  bool
     */
    public static function isCli()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * Send message mail
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
        $message   = wordwrap(implode(PHP_EOL, $message), 70);

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
                foreach ($funcInfo['parameters'] as $argName => $argInfo) {

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
     * @param        $query
     * @param string $sqlName
     * @param bool   $nl2br
     * @return JBDump
     */
    public static function sql($query, $sqlName = 'SQL Query', $nl2br = false)
    {
        // Joomla hack
        if (defined('_JEXEC')) {
            $config = new JConfig();
            $prefix = $config->dbprefix;
            $query  = str_replace('#__', $prefix, $query);
        }

        if (class_exists('JBDump_SqlFormatter')) {
            $sqlHtml = JBDump_SqlFormatter::format($query);
            return self::i()->dump($sqlHtml, $sqlName . '::html');
        }

        return self::i()->dump($query, $sqlName . '::html');
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

            $out .= "," . PHP_EOL;
        }

        if (!empty($out)) {
            $out = substr($out, 0, -2);
        }

        $out = "{" . PHP_EOL . $out . PHP_EOL . str_repeat("    ", $indent) . "}";

        return $out;
    }

    /**
     * @param array $data
     * @param bool  $sample
     * @return bool|float
     */
    protected function _stdDev(array $data, $sample = false)
    {
        $n = count($data);
        if ($n === 0) {
            trigger_error("The array has zero elements", E_USER_WARNING);
            return false;
        }

        if ($sample && $n === 1) {
            trigger_error("The array has only 1 element", E_USER_WARNING);
            return false;
        }

        $mean  = array_sum($data) / $n;
        $carry = 0.0;

        foreach ($data as $val) {
            $d = ((double)$val) - $mean;
            $carry += $d * $d;
        };

        if ($sample) {
            --$n;
        }

        return sqrt($carry / $n);
    }
}

/**
 * SQL Formatter is a collection of utilities for debugging SQL queries.
 * It includes methods for formatting, syntax highlighting, removing comments, etc.
 * @package    SqlFormatter
 * @author     Jeremy Dorn <jeremy@jeremydorn.com>
 * @author     Florin Patan <florinpatan@gmail.com>
 * @copyright  2013 Jeremy Dorn
 * @license    http://opensource.org/licenses/MIT
 * @link       http://github.com/jdorn/sql-formatter
 * @version    1.2.18
 */
class JBDump_SqlFormatter
{
    // Constants for token types
    const TOKEN_TYPE_WHITESPACE        = 0;
    const TOKEN_TYPE_WORD              = 1;
    const TOKEN_TYPE_QUOTE             = 2;
    const TOKEN_TYPE_BACKTICK_QUOTE    = 3;
    const TOKEN_TYPE_RESERVED          = 4;
    const TOKEN_TYPE_RESERVED_TOPLEVEL = 5;
    const TOKEN_TYPE_RESERVED_NEWLINE  = 6;
    const TOKEN_TYPE_BOUNDARY          = 7;
    const TOKEN_TYPE_COMMENT           = 8;
    const TOKEN_TYPE_BLOCK_COMMENT     = 9;
    const TOKEN_TYPE_NUMBER            = 10;
    const TOKEN_TYPE_ERROR             = 11;
    const TOKEN_TYPE_VARIABLE          = 12;

    // Constants for different components of a token
    const TOKEN_TYPE  = 0;
    const TOKEN_VALUE = 1;

    // Reserved words (for syntax highlighting)
    protected static $reserved = array(
        'ACCESSIBLE', 'ACTION', 'AGAINST', 'AGGREGATE', 'ALGORITHM', 'ALL', 'ALTER', 'ANALYSE', 'ANALYZE', 'AS', 'ASC',
        'AUTOCOMMIT', 'AUTO_INCREMENT', 'BACKUP', 'BEGIN', 'BETWEEN', 'BINLOG', 'BOTH', 'CASCADE', 'CASE', 'CHANGE', 'CHANGED', 'CHARACTER SET',
        'CHARSET', 'CHECK', 'CHECKSUM', 'COLLATE', 'COLLATION', 'COLUMN', 'COLUMNS', 'COMMENT', 'COMMIT', 'COMMITTED', 'COMPRESSED', 'CONCURRENT',
        'CONSTRAINT', 'CONTAINS', 'CONVERT', 'CREATE', 'CROSS', 'CURRENT_TIMESTAMP', 'DATABASE', 'DATABASES', 'DAY', 'DAY_HOUR', 'DAY_MINUTE',
        'DAY_SECOND', 'DEFAULT', 'DEFINER', 'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV',
        'DO', 'DUMPFILE', 'DUPLICATE', 'DYNAMIC', 'ELSE', 'ENCLOSED', 'END', 'ENGINE', 'ENGINE_TYPE', 'ENGINES', 'ESCAPE', 'ESCAPED', 'EVENTS', 'EXEC',
        'EXECUTE', 'EXISTS', 'EXPLAIN', 'EXTENDED', 'FAST', 'FIELDS', 'FILE', 'FIRST', 'FIXED', 'FLUSH', 'FOR', 'FORCE', 'FOREIGN', 'FULL', 'FULLTEXT',
        'FUNCTION', 'GLOBAL', 'GRANT', 'GRANTS', 'GROUP_CONCAT', 'HEAP', 'HIGH_PRIORITY', 'HOSTS', 'HOUR', 'HOUR_MINUTE',
        'HOUR_SECOND', 'IDENTIFIED', 'IF', 'IFNULL', 'IGNORE', 'IN', 'INDEX', 'INDEXES', 'INFILE', 'INSERT', 'INSERT_ID', 'INSERT_METHOD', 'INTERVAL',
        'INTO', 'INVOKER', 'IS', 'ISOLATION', 'KEY', 'KEYS', 'KILL', 'LAST_INSERT_ID', 'LEADING', 'LEVEL', 'LIKE', 'LINEAR',
        'LINES', 'LOAD', 'LOCAL', 'LOCK', 'LOCKS', 'LOGS', 'LOW_PRIORITY', 'MARIA', 'MASTER', 'MASTER_CONNECT_RETRY', 'MASTER_HOST', 'MASTER_LOG_FILE',
        'MATCH', 'MAX_CONNECTIONS_PER_HOUR', 'MAX_QUERIES_PER_HOUR', 'MAX_ROWS', 'MAX_UPDATES_PER_HOUR', 'MAX_USER_CONNECTIONS',
        'MEDIUM', 'MERGE', 'MINUTE', 'MINUTE_SECOND', 'MIN_ROWS', 'MODE', 'MODIFY',
        'MONTH', 'MRG_MYISAM', 'MYISAM', 'NAMES', 'NATURAL', 'NOT', 'NOW()', 'NULL', 'OFFSET', 'ON', 'OPEN', 'OPTIMIZE', 'OPTION', 'OPTIONALLY',
        'ON UPDATE', 'ON DELETE', 'OUTFILE', 'PACK_KEYS', 'PAGE', 'PARTIAL', 'PARTITION', 'PARTITIONS', 'PASSWORD', 'PRIMARY', 'PRIVILEGES', 'PROCEDURE',
        'PROCESS', 'PROCESSLIST', 'PURGE', 'QUICK', 'RANGE', 'RAID0', 'RAID_CHUNKS', 'RAID_CHUNKSIZE', 'RAID_TYPE', 'READ', 'READ_ONLY',
        'READ_WRITE', 'REFERENCES', 'REGEXP', 'RELOAD', 'RENAME', 'REPAIR', 'REPEATABLE', 'REPLACE', 'REPLICATION', 'RESET', 'RESTORE', 'RESTRICT',
        'RETURN', 'RETURNS', 'REVOKE', 'RLIKE', 'ROLLBACK', 'ROW', 'ROWS', 'ROW_FORMAT', 'SECOND', 'SECURITY', 'SEPARATOR',
        'SERIALIZABLE', 'SESSION', 'SHARE', 'SHOW', 'SHUTDOWN', 'SLAVE', 'SONAME', 'SOUNDS', 'SQL', 'SQL_AUTO_IS_NULL', 'SQL_BIG_RESULT',
        'SQL_BIG_SELECTS', 'SQL_BIG_TABLES', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_LOG_BIN', 'SQL_LOG_OFF', 'SQL_LOG_UPDATE',
        'SQL_LOW_PRIORITY_UPDATES', 'SQL_MAX_JOIN_SIZE', 'SQL_QUOTE_SHOW_CREATE', 'SQL_SAFE_UPDATES', 'SQL_SELECT_LIMIT', 'SQL_SLAVE_SKIP_COUNTER',
        'SQL_SMALL_RESULT', 'SQL_WARNINGS', 'SQL_CACHE', 'SQL_NO_CACHE', 'START', 'STARTING', 'STATUS', 'STOP', 'STORAGE',
        'STRAIGHT_JOIN', 'STRING', 'STRIPED', 'SUPER', 'TABLE', 'TABLES', 'TEMPORARY', 'TERMINATED', 'THEN', 'TO', 'TRAILING', 'TRANSACTIONAL', 'TRUE',
        'TRUNCATE', 'TYPE', 'TYPES', 'UNCOMMITTED', 'UNIQUE', 'UNLOCK', 'UNSIGNED', 'USAGE', 'USE', 'USING', 'VARIABLES',
        'VIEW', 'WHEN', 'WITH', 'WORK', 'WRITE', 'YEAR_MONTH'
    );

    // For SQL formatting
    // These keywords will all be on their own line
    protected static $reserved_toplevel = array(
        'SELECT', 'FROM', 'WHERE', 'SET', 'ORDER BY', 'GROUP BY', 'LIMIT', 'DROP',
        'VALUES', 'UPDATE', 'HAVING', 'ADD', 'AFTER', 'ALTER TABLE', 'DELETE FROM', 'UNION ALL', 'UNION', 'EXCEPT', 'INTERSECT'
    );

    protected static $reserved_newline = array(
        'LEFT OUTER JOIN', 'RIGHT OUTER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'OUTER JOIN', 'INNER JOIN', 'JOIN', 'XOR', 'OR', 'AND'
    );

    protected static $functions = array(
        'ABS', 'ACOS', 'ADDDATE', 'ADDTIME', 'AES_DECRYPT', 'AES_ENCRYPT', 'AREA', 'ASBINARY', 'ASCII', 'ASIN', 'ASTEXT', 'ATAN', 'ATAN2',
        'AVG', 'BDMPOLYFROMTEXT', 'BDMPOLYFROMWKB', 'BDPOLYFROMTEXT', 'BDPOLYFROMWKB', 'BENCHMARK', 'BIN', 'BIT_AND', 'BIT_COUNT', 'BIT_LENGTH',
        'BIT_OR', 'BIT_XOR', 'BOUNDARY', 'BUFFER', 'CAST', 'CEIL', 'CEILING', 'CENTROID', 'CHAR', 'CHARACTER_LENGTH', 'CHARSET', 'CHAR_LENGTH',
        'COALESCE', 'COERCIBILITY', 'COLLATION', 'COMPRESS', 'CONCAT', 'CONCAT_WS', 'CONNECTION_ID', 'CONTAINS', 'CONV', 'CONVERT', 'CONVERT_TZ',
        'CONVEXHULL', 'COS', 'COT', 'COUNT', 'CRC32', 'CROSSES', 'CURDATE', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER',
        'CURTIME', 'DATABASE', 'DATE', 'DATEDIFF', 'DATE_ADD', 'DATE_DIFF', 'DATE_FORMAT', 'DATE_SUB', 'DAY', 'DAYNAME', 'DAYOFMONTH', 'DAYOFWEEK',
        'DAYOFYEAR', 'DECODE', 'DEFAULT', 'DEGREES', 'DES_DECRYPT', 'DES_ENCRYPT', 'DIFFERENCE', 'DIMENSION', 'DISJOINT', 'DISTANCE', 'ELT', 'ENCODE',
        'ENCRYPT', 'ENDPOINT', 'ENVELOPE', 'EQUALS', 'EXP', 'EXPORT_SET', 'EXTERIORRING', 'EXTRACT', 'EXTRACTVALUE', 'FIELD', 'FIND_IN_SET', 'FLOOR',
        'FORMAT', 'FOUND_ROWS', 'FROM_DAYS', 'FROM_UNIXTIME', 'GEOMCOLLFROMTEXT', 'GEOMCOLLFROMWKB', 'GEOMETRYCOLLECTION', 'GEOMETRYCOLLECTIONFROMTEXT',
        'GEOMETRYCOLLECTIONFROMWKB', 'GEOMETRYFROMTEXT', 'GEOMETRYFROMWKB', 'GEOMETRYN', 'GEOMETRYTYPE', 'GEOMFROMTEXT', 'GEOMFROMWKB', 'GET_FORMAT',
        'GET_LOCK', 'GLENGTH', 'GREATEST', 'GROUP_CONCAT', 'GROUP_UNIQUE_USERS', 'HEX', 'HOUR', 'IF', 'IFNULL', 'INET_ATON', 'INET_NTOA', 'INSERT', 'INSTR',
        'INTERIORRINGN', 'INTERSECTION', 'INTERSECTS', 'INTERVAL', 'ISCLOSED', 'ISEMPTY', 'ISNULL', 'ISRING', 'ISSIMPLE', 'IS_FREE_LOCK', 'IS_USED_LOCK',
        'LAST_DAY', 'LAST_INSERT_ID', 'LCASE', 'LEAST', 'LEFT', 'LENGTH', 'LINEFROMTEXT', 'LINEFROMWKB', 'LINESTRING', 'LINESTRINGFROMTEXT', 'LINESTRINGFROMWKB',
        'LN', 'LOAD_FILE', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCATE', 'LOG', 'LOG10', 'LOG2', 'LOWER', 'LPAD', 'LTRIM', 'MAKEDATE', 'MAKETIME', 'MAKE_SET',
        'MASTER_POS_WAIT', 'MAX', 'MBRCONTAINS', 'MBRDISJOINT', 'MBREQUAL', 'MBRINTERSECTS', 'MBROVERLAPS', 'MBRTOUCHES', 'MBRWITHIN', 'MD5', 'MICROSECOND',
        'MID', 'MIN', 'MINUTE', 'MLINEFROMTEXT', 'MLINEFROMWKB', 'MOD', 'MONTH', 'MONTHNAME', 'MPOINTFROMTEXT', 'MPOINTFROMWKB', 'MPOLYFROMTEXT', 'MPOLYFROMWKB',
        'MULTILINESTRING', 'MULTILINESTRINGFROMTEXT', 'MULTILINESTRINGFROMWKB', 'MULTIPOINT', 'MULTIPOINTFROMTEXT', 'MULTIPOINTFROMWKB', 'MULTIPOLYGON',
        'MULTIPOLYGONFROMTEXT', 'MULTIPOLYGONFROMWKB', 'NAME_CONST', 'NULLIF', 'NUMGEOMETRIES', 'NUMINTERIORRINGS', 'NUMPOINTS', 'OCT', 'OCTET_LENGTH',
        'OLD_PASSWORD', 'ORD', 'OVERLAPS', 'PASSWORD', 'PERIOD_ADD', 'PERIOD_DIFF', 'PI', 'POINT', 'POINTFROMTEXT', 'POINTFROMWKB', 'POINTN', 'POINTONSURFACE',
        'POLYFROMTEXT', 'POLYFROMWKB', 'POLYGON', 'POLYGONFROMTEXT', 'POLYGONFROMWKB', 'POSITION', 'POW', 'POWER', 'QUARTER', 'QUOTE', 'RADIANS', 'RAND',
        'RELATED', 'RELEASE_LOCK', 'REPEAT', 'REPLACE', 'REVERSE', 'RIGHT', 'ROUND', 'ROW_COUNT', 'RPAD', 'RTRIM', 'SCHEMA', 'SECOND', 'SEC_TO_TIME',
        'SESSION_USER', 'SHA', 'SHA1', 'SIGN', 'SIN', 'SLEEP', 'SOUNDEX', 'SPACE', 'SQRT', 'SRID', 'STARTPOINT', 'STD', 'STDDEV', 'STDDEV_POP', 'STDDEV_SAMP',
        'STRCMP', 'STR_TO_DATE', 'SUBDATE', 'SUBSTR', 'SUBSTRING', 'SUBSTRING_INDEX', 'SUBTIME', 'SUM', 'SYMDIFFERENCE', 'SYSDATE', 'SYSTEM_USER', 'TAN',
        'TIME', 'TIMEDIFF', 'TIMESTAMP', 'TIMESTAMPADD', 'TIMESTAMPDIFF', 'TIME_FORMAT', 'TIME_TO_SEC', 'TOUCHES', 'TO_DAYS', 'TRIM', 'TRUNCATE', 'UCASE',
        'UNCOMPRESS', 'UNCOMPRESSED_LENGTH', 'UNHEX', 'UNIQUE_USERS', 'UNIX_TIMESTAMP', 'UPDATEXML', 'UPPER', 'USER', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP',
        'UUID', 'VARIANCE', 'VAR_POP', 'VAR_SAMP', 'VERSION', 'WEEK', 'WEEKDAY', 'WEEKOFYEAR', 'WITHIN', 'X', 'Y', 'YEAR', 'YEARWEEK'
    );

    // Punctuation that can be used as a boundary between other tokens
    protected static $boundaries = array(',', ';', ':', ')', '(', '.', '=', '<', '>', '+', '-', '*', '/', '!', '^', '%', '|', '&', '#');

    // For HTML syntax highlighting
    // Styles applied to different token types
    public static $quote_attributes = 'style="color:#F700DA;font-weight:bold;"';
    public static $backtick_quote_attributes = 'style="color: purple;"';
    public static $reserved_attributes = 'style="font-weight:bold;color:#00f;"';
    public static $boundary_attributes = 'style="color:#000;"';
    public static $number_attributes = 'style="color:#0a0;font-weight:bold;"';
    public static $word_attributes = 'style="color: #333;"';
    public static $error_attributes = 'style="background-color: red;"';
    public static $comment_attributes = 'style="color: #aaa;"';
    public static $variable_attributes = 'style="color: orange;"';
    public static $pre_attributes = '';

    // Boolean - whether or not the current environment is the CLI
    // This affects the type of syntax highlighting
    // If not defined, it will be determined automatically
    public static $cli;

    // For CLI syntax highlighting
    public static $cli_quote = "\x1b[34;1m";
    public static $cli_backtick_quote = "\x1b[35;1m";
    public static $cli_reserved = "\x1b[37m";
    public static $cli_boundary = "";
    public static $cli_number = "\x1b[32;1m";
    public static $cli_word = "";
    public static $cli_error = "\x1b[31;1;7m";
    public static $cli_comment = "\x1b[30;1m";
    public static $cli_functions = "\x1b[37m";
    public static $cli_variable = "\x1b[36;1m";

    // The tab character to use when formatting SQL
    public static $tab = '    ';

    // This flag tells us if queries need to be enclosed in <pre> tags
    public static $use_pre = true;

    // This flag tells us if SqlFormatted has been initialized
    protected static $init;

    // Regular expressions for tokenizing
    protected static $regex_boundaries;
    protected static $regex_reserved;
    protected static $regex_reserved_newline;
    protected static $regex_reserved_toplevel;
    protected static $regex_function;

    // Cache variables
    // Only tokens shorter than this size will be cached.  Somewhere between 10 and 20 seems to work well for most cases.
    public static $max_cachekey_size = 15;
    protected static $token_cache = array();
    protected static $cache_hits = 0;
    protected static $cache_misses = 0;

    /**
     * Get stats about the token cache
     * @return Array An array containing the keys 'hits', 'misses', 'entries', and 'size' in bytes
     */
    public static function getCacheStats()
    {
        return array(
            'hits'    => self::$cache_hits,
            'misses'  => self::$cache_misses,
            'entries' => count(self::$token_cache),
            'size'    => strlen(serialize(self::$token_cache))
        );
    }

    /**
     * Stuff that only needs to be done once.  Builds regular expressions and sorts the reserved words.
     */
    protected static function init()
    {
        if (self::$init) {
            return;
        }

        // Sort reserved word list from longest word to shortest, 3x faster than usort
        $reservedMap = array_combine(self::$reserved, array_map('strlen', self::$reserved));
        arsort($reservedMap);
        self::$reserved = array_keys($reservedMap);

        // Set up regular expressions
        self::$regex_boundaries        = '(' . implode('|', array_map(array(__CLASS__, 'quote_regex'), self::$boundaries)) . ')';
        self::$regex_reserved          = '(' . implode('|', array_map(array(__CLASS__, 'quote_regex'), self::$reserved)) . ')';
        self::$regex_reserved_toplevel = str_replace(' ', '\\s+', '(' . implode('|', array_map(array(__CLASS__, 'quote_regex'), self::$reserved_toplevel)) . ')');
        self::$regex_reserved_newline  = str_replace(' ', '\\s+', '(' . implode('|', array_map(array(__CLASS__, 'quote_regex'), self::$reserved_newline)) . ')');

        self::$regex_function = '(' . implode('|', array_map(array(__CLASS__, 'quote_regex'), self::$functions)) . ')';

        self::$init = true;
    }

    /**
     * Return the next token and token type in a SQL string.
     * Quoted strings, comments, reserved words, whitespace, and punctuation are all their own tokens.
     * @param String $string   The SQL string
     * @param array  $previous The result of the previous getNextToken() call
     * @return Array An associative array containing the type and value of the token.
     */
    protected static function getNextToken($string, $previous = null)
    {
        // Whitespace
        if (preg_match('/^\s+/', $string, $matches)) {
            return array(
                self::TOKEN_VALUE => $matches[0],
                self::TOKEN_TYPE  => self::TOKEN_TYPE_WHITESPACE
            );
        }

        // Comment
        if ($string[0] === '#' || (isset($string[1]) && ($string[0] === '-' && $string[1] === '-') || ($string[0] === '/' && $string[1] === '*'))) {
            // Comment until end of line
            if ($string[0] === '-' || $string[0] === '#') {
                $last = strpos($string, PHP_EOL);
                $type = self::TOKEN_TYPE_COMMENT;
            } else { // Comment until closing comment tag
                $last = strpos($string, "*/", 2) + 2;
                $type = self::TOKEN_TYPE_BLOCK_COMMENT;
            }

            if ($last === false) {
                $last = strlen($string);
            }

            return array(
                self::TOKEN_VALUE => substr($string, 0, $last),
                self::TOKEN_TYPE  => $type
            );
        }

        // Quoted String
        if ($string[0] === '"' || $string[0] === '\'' || $string[0] === '`' || $string[0] === '[') {
            $return = array(
                self::TOKEN_TYPE  => (($string[0] === '`' || $string[0] === '[') ? self::TOKEN_TYPE_BACKTICK_QUOTE : self::TOKEN_TYPE_QUOTE),
                self::TOKEN_VALUE => self::getQuotedString($string)
            );

            return $return;
        }

        // User-defined Variable
        if ($string[0] === '@' && isset($string[1])) {
            $ret = array(
                self::TOKEN_VALUE => null,
                self::TOKEN_TYPE  => self::TOKEN_TYPE_VARIABLE
            );

            // If the variable name is quoted
            if ($string[1] === '"' || $string[1] === '\'' || $string[1] === '`') {
                $ret[self::TOKEN_VALUE] = '@' . self::getQuotedString(substr($string, 1));
            } // Non-quoted variable name
            else {
                preg_match('/^(@[a-zA-Z0-9\._\$]+)/', $string, $matches);
                if ($matches) {
                    $ret[self::TOKEN_VALUE] = $matches[1];
                }
            }

            if ($ret[self::TOKEN_VALUE] !== null) {
                return $ret;
            }
        }

        // Number (decimal, binary, or hex)
        if (preg_match('/^([0-9]+(\.[0-9]+)?|0x[0-9a-fA-F]+|0b[01]+)($|\s|"\'`|' . self::$regex_boundaries . ')/', $string, $matches)) {
            return array(
                self::TOKEN_VALUE => $matches[1],
                self::TOKEN_TYPE  => self::TOKEN_TYPE_NUMBER
            );
        }

        // Boundary Character (punctuation and symbols)
        if (preg_match('/^(' . self::$regex_boundaries . ')/', $string, $matches)) {
            return array(
                self::TOKEN_VALUE => $matches[1],
                self::TOKEN_TYPE  => self::TOKEN_TYPE_BOUNDARY
            );
        }

        // A reserved word cannot be preceded by a '.'
        // this makes it so in "mytable.from", "from" is not considered a reserved word
        if (!$previous || !isset($previous[self::TOKEN_VALUE]) || $previous[self::TOKEN_VALUE] !== '.') {
            $upper = strtoupper($string);
            // Top Level Reserved Word
            if (preg_match('/^(' . self::$regex_reserved_toplevel . ')($|\s|' . self::$regex_boundaries . ')/', $upper, $matches)) {
                return array(
                    self::TOKEN_TYPE  => self::TOKEN_TYPE_RESERVED_TOPLEVEL,
                    self::TOKEN_VALUE => substr($string, 0, strlen($matches[1]))
                );
            }
            // Newline Reserved Word
            if (preg_match('/^(' . self::$regex_reserved_newline . ')($|\s|' . self::$regex_boundaries . ')/', $upper, $matches)) {
                return array(
                    self::TOKEN_TYPE  => self::TOKEN_TYPE_RESERVED_NEWLINE,
                    self::TOKEN_VALUE => substr($string, 0, strlen($matches[1]))
                );
            }
            // Other Reserved Word
            if (preg_match('/^(' . self::$regex_reserved . ')($|\s|' . self::$regex_boundaries . ')/', $upper, $matches)) {
                return array(
                    self::TOKEN_TYPE  => self::TOKEN_TYPE_RESERVED,
                    self::TOKEN_VALUE => substr($string, 0, strlen($matches[1]))
                );
            }
        }

        // A function must be suceeded by '('
        // this makes it so "count(" is considered a function, but "count" alone is not
        $upper = strtoupper($string);
        // function
        if (preg_match('/^(' . self::$regex_function . '[(]|\s|[)])/', $upper, $matches)) {
            return array(
                self::TOKEN_TYPE  => self::TOKEN_TYPE_RESERVED,
                self::TOKEN_VALUE => substr($string, 0, strlen($matches[1]) - 1)
            );
        }

        // Non reserved word
        preg_match('/^(.*?)($|\s|["\'`]|' . self::$regex_boundaries . ')/', $string, $matches);

        return array(
            self::TOKEN_VALUE => $matches[1],
            self::TOKEN_TYPE  => self::TOKEN_TYPE_WORD
        );
    }

    protected static function getQuotedString($string)
    {
        $ret = null;

        // This checks for the following patterns:
        // 1. backtick quoted string using `` to escape
        // 2. square bracket quoted string (SQL Server) using ]] to escape
        // 3. double quoted string using "" or \" to escape
        // 4. single quoted string using '' or \' to escape
        if (preg_match('/^(((`[^`]*($|`))+)|((\[[^\]]*($|\]))(\][^\]]*($|\]))*)|(("[^"\\\\]*(?:\\\\.[^"\\\\]*)*("|$))+)|((\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*(\'|$))+))/s', $string, $matches)) {
            $ret = $matches[1];
        }

        return $ret;
    }

    /**
     * Takes a SQL string and breaks it into tokens.
     * Each token is an associative array with type and value.
     * @param String $string The SQL string
     * @return Array An array of tokens.
     */
    protected static function tokenize($string)
    {
        self::init();

        $tokens = array();

        // Used for debugging if there is an error while tokenizing the string
        $original_length = strlen($string);

        // Used to make sure the string keeps shrinking on each iteration
        $old_string_len = strlen($string) + 1;

        $token = null;

        $current_length = strlen($string);

        // Keep processing the string until it is empty
        while ($current_length) {
            // If the string stopped shrinking, there was a problem
            if ($old_string_len <= $current_length) {
                $tokens[] = array(
                    self::TOKEN_VALUE => $string,
                    self::TOKEN_TYPE  => self::TOKEN_TYPE_ERROR
                );

                return $tokens;
            }
            $old_string_len = $current_length;

            // Determine if we can use caching
            if ($current_length >= self::$max_cachekey_size) {
                $cacheKey = substr($string, 0, self::$max_cachekey_size);
            } else {
                $cacheKey = false;
            }

            // See if the token is already cached
            if ($cacheKey && isset(self::$token_cache[$cacheKey])) {
                // Retrieve from cache
                $token        = self::$token_cache[$cacheKey];
                $token_length = strlen($token[self::TOKEN_VALUE]);
                self::$cache_hits++;
            } else {
                // Get the next token and the token type
                $token        = self::getNextToken($string, $token);
                $token_length = strlen($token[self::TOKEN_VALUE]);
                self::$cache_misses++;

                // If the token is shorter than the max length, store it in cache
                if ($cacheKey && $token_length < self::$max_cachekey_size) {
                    self::$token_cache[$cacheKey] = $token;
                }
            }

            $tokens[] = $token;

            // Advance the string
            $string = substr($string, $token_length);

            $current_length -= $token_length;
        }

        return $tokens;
    }

    /**
     * Format the whitespace in a SQL string to make it easier to read.
     * @param String  $string    The SQL string
     * @param boolean $highlight If true, syntax highlighting will also be performed
     * @return String The SQL string with HTML styles and formatting wrapped in a <pre> tag
     */
    public static function format($string, $highlight = true)
    {
        // This variable will be populated with formatted html
        $return = '';

        // Use an actual tab while formatting and then switch out with self::$tab at the end
        $tab = "\t";

        $indent_level            = 0;
        $newline                 = false;
        $inline_parentheses      = false;
        $increase_special_indent = false;
        $increase_block_indent   = false;
        $indent_types            = array();
        $added_newline           = false;
        $inline_count            = 0;
        $inline_indented         = false;
        $clause_limit            = false;

        // Tokenize String
        $original_tokens = self::tokenize($string);

        // Remove existing whitespace
        $tokens = array();
        foreach ($original_tokens as $i => $token) {
            if ($token[self::TOKEN_TYPE] !== self::TOKEN_TYPE_WHITESPACE) {
                $token['i'] = $i;
                $tokens[]   = $token;
            }
        }

        // Format token by token
        foreach ($tokens as $i => $token) {
            // Get highlighted token if doing syntax highlighting
            if ($highlight) {
                $highlighted = self::highlightToken($token);
            } else { // If returning raw text
                $highlighted = $token[self::TOKEN_VALUE];
            }

            // If we are increasing the special indent level now
            if ($increase_special_indent) {
                $indent_level++;
                $increase_special_indent = false;
                array_unshift($indent_types, 'special');
            }
            // If we are increasing the block indent level now
            if ($increase_block_indent) {
                $indent_level++;
                $increase_block_indent = false;
                array_unshift($indent_types, 'block');
            }

            // If we need a new line before the token
            if ($newline) {
                $return .= PHP_EOL . str_repeat($tab, $indent_level);
                $newline       = false;
                $added_newline = true;
            } else {
                $added_newline = false;
            }

            // Display comments directly where they appear in the source
            if ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_COMMENT || $token[self::TOKEN_TYPE] === self::TOKEN_TYPE_BLOCK_COMMENT) {
                if ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_BLOCK_COMMENT) {
                    $indent = str_repeat($tab, $indent_level);
                    $return .= PHP_EOL . $indent;
                    $highlighted = str_replace(PHP_EOL, PHP_EOL . $indent, $highlighted);
                }

                $return .= $highlighted;
                $newline = true;
                continue;
            }

            if ($inline_parentheses) {
                // End of inline parentheses
                if ($token[self::TOKEN_VALUE] === ')') {
                    $return = rtrim($return, ' ');

                    if ($inline_indented) {
                        array_shift($indent_types);
                        $indent_level--;
                        $return .= PHP_EOL . str_repeat($tab, $indent_level);
                    }

                    $inline_parentheses = false;

                    $return .= $highlighted . ' ';
                    continue;
                }

                if ($token[self::TOKEN_VALUE] === ',') {
                    if ($inline_count >= 30) {
                        $inline_count = 0;
                        $newline      = true;
                    }
                }

                $inline_count += strlen($token[self::TOKEN_VALUE]);
            }

            // Opening parentheses increase the block indent level and start a new line
            if ($token[self::TOKEN_VALUE] === '(') {
                // First check if this should be an inline parentheses block
                // Examples are "NOW()", "COUNT(*)", "int(10)", key(`somecolumn`), DECIMAL(7,2)
                // Allow up to 3 non-whitespace tokens inside inline parentheses
                $length = 0;
                for ($j = 1; $j <= 250; $j++) {
                    // Reached end of string
                    if (!isset($tokens[$i + $j])) {
                        break;
                    }

                    $next = $tokens[$i + $j];

                    // Reached closing parentheses, able to inline it
                    if ($next[self::TOKEN_VALUE] === ')') {
                        $inline_parentheses = true;
                        $inline_count       = 0;
                        $inline_indented    = false;
                        break;
                    }

                    // Reached an invalid token for inline parentheses
                    if ($next[self::TOKEN_VALUE] === ';' || $next[self::TOKEN_VALUE] === '(') {
                        break;
                    }

                    // Reached an invalid token type for inline parentheses
                    if ($next[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED_TOPLEVEL || $next[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED_NEWLINE || $next[self::TOKEN_TYPE] === self::TOKEN_TYPE_COMMENT || $next[self::TOKEN_TYPE] === self::TOKEN_TYPE_BLOCK_COMMENT) {
                        break;
                    }

                    $length += strlen($next[self::TOKEN_VALUE]);
                }

                if ($inline_parentheses && $length > 30) {
                    $increase_block_indent = true;
                    $inline_indented       = true;
                    $newline               = true;
                }

                // Take out the preceding space unless there was whitespace there in the original query
                if (isset($original_tokens[$token['i'] - 1]) && $original_tokens[$token['i'] - 1][self::TOKEN_TYPE] !== self::TOKEN_TYPE_WHITESPACE) {
                    $return = rtrim($return, ' ');
                }

                if (!$inline_parentheses) {
                    $increase_block_indent = true;
                    // Add a newline after the parentheses
                    $newline = true;
                }

            } // Closing parentheses decrease the block indent level
            elseif ($token[self::TOKEN_VALUE] === ')') {
                // Remove whitespace before the closing parentheses
                $return = rtrim($return, ' ');

                $indent_level--;

                // Reset indent level
                while ($j = array_shift($indent_types)) {
                    if ($j === 'special') {
                        $indent_level--;
                    } else {
                        break;
                    }
                }

                if ($indent_level < 0) {
                    // This is an error
                    $indent_level = 0;

                    if ($highlight) {
                        $return .= PHP_EOL . self::highlightError($token[self::TOKEN_VALUE]);
                        continue;
                    }
                }

                // Add a newline before the closing parentheses (if not already added)
                if (!$added_newline) {
                    $return .= PHP_EOL . str_repeat($tab, $indent_level);
                }
            } // Top level reserved words start a new line and increase the special indent level
            elseif ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED_TOPLEVEL) {
                $increase_special_indent = true;

                // If the last indent type was 'special', decrease the special indent for this round
                reset($indent_types);
                if (current($indent_types) === 'special') {
                    $indent_level--;
                    array_shift($indent_types);
                }

                // Add a newline after the top level reserved word
                $newline = true;
                // Add a newline before the top level reserved word (if not already added)
                if (!$added_newline) {
                    $return .= PHP_EOL . str_repeat($tab, $indent_level);
                } // If we already added a newline, redo the indentation since it may be different now
                else {
                    $return = rtrim($return, $tab) . str_repeat($tab, $indent_level);
                }

                // If the token may have extra whitespace
                if (strpos($token[self::TOKEN_VALUE], ' ') !== false || strpos($token[self::TOKEN_VALUE], PHP_EOL) !== false || strpos($token[self::TOKEN_VALUE], "\t") !== false) {
                    $highlighted = preg_replace('/\s+/', ' ', $highlighted);
                }
                //if SQL 'LIMIT' clause, start variable to reset newline
                if ($token[self::TOKEN_VALUE] === 'LIMIT' && !$inline_parentheses) {
                    $clause_limit = true;
                }
            } // Checks if we are out of the limit clause
            elseif ($clause_limit && $token[self::TOKEN_VALUE] !== "," && $token[self::TOKEN_TYPE] !== self::TOKEN_TYPE_NUMBER && $token[self::TOKEN_TYPE] !== self::TOKEN_TYPE_WHITESPACE) {
                $clause_limit = false;
            } // Commas start a new line (unless within inline parentheses or SQL 'LIMIT' clause)
            elseif ($token[self::TOKEN_VALUE] === ',' && !$inline_parentheses) {
                //If the previous TOKEN_VALUE is 'LIMIT', resets new line
                if ($clause_limit === true) {
                    $newline      = false;
                    $clause_limit = false;
                } // All other cases of commas
                else {
                    $newline = true;
                }
            } // Newline reserved words start a new line
            elseif ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED_NEWLINE) {
                // Add a newline before the reserved word (if not already added)
                if (!$added_newline) {
                    $return .= PHP_EOL . str_repeat($tab, $indent_level);
                }

                // If the token may have extra whitespace
                if (strpos($token[self::TOKEN_VALUE], ' ') !== false || strpos($token[self::TOKEN_VALUE], PHP_EOL) !== false || strpos($token[self::TOKEN_VALUE], "\t") !== false) {
                    $highlighted = preg_replace('/\s+/', ' ', $highlighted);
                }
            } // Multiple boundary characters in a row should not have spaces between them (not including parentheses)
            elseif ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_BOUNDARY) {
                if (isset($tokens[$i - 1]) && $tokens[$i - 1][self::TOKEN_TYPE] === self::TOKEN_TYPE_BOUNDARY) {
                    if (isset($original_tokens[$token['i'] - 1]) && $original_tokens[$token['i'] - 1][self::TOKEN_TYPE] !== self::TOKEN_TYPE_WHITESPACE) {
                        $return = rtrim($return, ' ');
                    }
                }
            }

            // If the token shouldn't have a space before it
            if ($token[self::TOKEN_VALUE] === '.' || $token[self::TOKEN_VALUE] === ',' || $token[self::TOKEN_VALUE] === ';') {
                $return = rtrim($return, ' ');
            }

            $return .= $highlighted . ' ';

            // If the token shouldn't have a space after it
            if ($token[self::TOKEN_VALUE] === '(' || $token[self::TOKEN_VALUE] === '.') {
                $return = rtrim($return, ' ');
            }

            // If this is the "-" of a negative number, it shouldn't have a space after it
            if ($token[self::TOKEN_VALUE] === '-' && isset($tokens[$i + 1]) && $tokens[$i + 1][self::TOKEN_TYPE] === self::TOKEN_TYPE_NUMBER && isset($tokens[$i - 1])) {
                $prev = $tokens[$i - 1][self::TOKEN_TYPE];
                if ($prev !== self::TOKEN_TYPE_QUOTE && $prev !== self::TOKEN_TYPE_BACKTICK_QUOTE && $prev !== self::TOKEN_TYPE_WORD && $prev !== self::TOKEN_TYPE_NUMBER) {
                    $return = rtrim($return, ' ');
                }
            }
        }

        // If there are unmatched parentheses
        if ($highlight && array_search('block', $indent_types) !== false) {
            $return .= PHP_EOL . self::highlightError("WARNING: unclosed parentheses or section");
        }

        // Replace tab characters with the configuration tab character
        $return = trim(str_replace("\t", self::$tab, $return));

        if ($highlight) {
            $return = self::output($return);
        }

        return $return;
    }

    /**
     * Add syntax highlighting to a SQL string
     * @param String $string The SQL string
     * @return String The SQL string with HTML styles applied
     */
    public static function highlight($string)
    {
        $tokens = self::tokenize($string);

        $return = '';

        foreach ($tokens as $token) {
            $return .= self::highlightToken($token);
        }

        return self::output($return);
    }

    /**
     * Split a SQL string into multiple queries.
     * Uses ";" as a query delimiter.
     * @param String $string The SQL string
     * @return Array An array of individual query strings without trailing semicolons
     */
    public static function splitQuery($string)
    {
        $queries       = array();
        $current_query = '';
        $empty         = true;

        $tokens = self::tokenize($string);

        foreach ($tokens as $token) {
            // If this is a query separator
            if ($token[self::TOKEN_VALUE] === ';') {
                if (!$empty) {
                    $queries[] = $current_query . ';';
                }
                $current_query = '';
                $empty         = true;
                continue;
            }

            // If this is a non-empty character
            if ($token[self::TOKEN_TYPE] !== self::TOKEN_TYPE_WHITESPACE && $token[self::TOKEN_TYPE] !== self::TOKEN_TYPE_COMMENT && $token[self::TOKEN_TYPE] !== self::TOKEN_TYPE_BLOCK_COMMENT) {
                $empty = false;
            }

            $current_query .= $token[self::TOKEN_VALUE];
        }

        if (!$empty) {
            $queries[] = trim($current_query);
        }

        return $queries;
    }

    /**
     * Remove all comments from a SQL string
     * @param String $string The SQL string
     * @return String The SQL string without comments
     */
    public static function removeComments($string)
    {
        $result = '';

        $tokens = self::tokenize($string);

        foreach ($tokens as $token) {
            // Skip comment tokens
            if ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_COMMENT || $token[self::TOKEN_TYPE] === self::TOKEN_TYPE_BLOCK_COMMENT) {
                continue;
            }

            $result .= $token[self::TOKEN_VALUE];
        }
        $result = self::format($result, false);

        return $result;
    }

    /**
     * Compress a query by collapsing white space and removing comments
     * @param String $string The SQL string
     * @return String The SQL string without comments
     */
    public static function compress($string)
    {
        $result = '';

        $tokens = self::tokenize($string);

        $whitespace = true;
        foreach ($tokens as $token) {
            // Skip comment tokens
            if ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_COMMENT || $token[self::TOKEN_TYPE] === self::TOKEN_TYPE_BLOCK_COMMENT) {
                continue;
            } // Remove extra whitespace in reserved words (e.g "OUTER     JOIN" becomes "OUTER JOIN")
            elseif ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED || $token[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED_NEWLINE || $token[self::TOKEN_TYPE] === self::TOKEN_TYPE_RESERVED_TOPLEVEL) {
                $token[self::TOKEN_VALUE] = preg_replace('/\s+/', ' ', $token[self::TOKEN_VALUE]);
            }

            if ($token[self::TOKEN_TYPE] === self::TOKEN_TYPE_WHITESPACE) {
                // If the last token was whitespace, don't add another one
                if ($whitespace) {
                    continue;
                } else {
                    $whitespace = true;
                    // Convert all whitespace to a single space
                    $token[self::TOKEN_VALUE] = ' ';
                }
            } else {
                $whitespace = false;
            }

            $result .= $token[self::TOKEN_VALUE];
        }

        return rtrim($result);
    }

    /**
     * Highlights a token depending on its type.
     * @param Array $token An associative array containing type and value.
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightToken($token)
    {
        $type = $token[self::TOKEN_TYPE];

        if (self::is_cli()) {
            $token = $token[self::TOKEN_VALUE];
        } else {
            if (defined('ENT_IGNORE')) {
                $token = htmlentities($token[self::TOKEN_VALUE], ENT_COMPAT | ENT_IGNORE, 'UTF-8');
            } else {
                $token = htmlentities($token[self::TOKEN_VALUE], ENT_COMPAT, 'UTF-8');
            }
        }

        if ($type === self::TOKEN_TYPE_BOUNDARY) {
            return self::highlightBoundary($token);
        } elseif ($type === self::TOKEN_TYPE_WORD) {
            return self::highlightWord($token);
        } elseif ($type === self::TOKEN_TYPE_BACKTICK_QUOTE) {
            return self::highlightBacktickQuote($token);
        } elseif ($type === self::TOKEN_TYPE_QUOTE) {
            return self::highlightQuote($token);
        } elseif ($type === self::TOKEN_TYPE_RESERVED) {
            return self::highlightReservedWord($token);
        } elseif ($type === self::TOKEN_TYPE_RESERVED_TOPLEVEL) {
            return self::highlightReservedWord($token);
        } elseif ($type === self::TOKEN_TYPE_RESERVED_NEWLINE) {
            return self::highlightReservedWord($token);
        } elseif ($type === self::TOKEN_TYPE_NUMBER) {
            return self::highlightNumber($token);
        } elseif ($type === self::TOKEN_TYPE_VARIABLE) {
            return self::highlightVariable($token);
        } elseif ($type === self::TOKEN_TYPE_COMMENT || $type === self::TOKEN_TYPE_BLOCK_COMMENT) {
            return self::highlightComment($token);
        }

        return $token;
    }

    /**
     * Highlights a quoted string
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightQuote($value)
    {
        if (self::is_cli()) {
            return self::$cli_quote . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$quote_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a backtick quoted string
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightBacktickQuote($value)
    {
        if (self::is_cli()) {
            return self::$cli_backtick_quote . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$backtick_quote_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a reserved word
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightReservedWord($value)
    {
        if (self::is_cli()) {
            return self::$cli_reserved . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$reserved_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a boundary token
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightBoundary($value)
    {
        if ($value === '(' || $value === ')') {
            return $value;
        }

        if (self::is_cli()) {
            return self::$cli_boundary . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$boundary_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a number
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightNumber($value)
    {
        if (self::is_cli()) {
            return self::$cli_number . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$number_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights an error
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightError($value)
    {
        if (self::is_cli()) {
            return self::$cli_error . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$error_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a comment
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightComment($value)
    {
        if (self::is_cli()) {
            return self::$cli_comment . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$comment_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a word token
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightWord($value)
    {
        if (self::is_cli()) {
            return self::$cli_word . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$word_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Highlights a variable token
     * @param String $value The token's value
     * @return String HTML code of the highlighted token.
     */
    protected static function highlightVariable($value)
    {
        if (self::is_cli()) {
            return self::$cli_variable . $value . "\x1b[0m";
        } else {
            return '<span ' . self::$variable_attributes . '>' . $value . '</span>';
        }
    }

    /**
     * Helper function for building regular expressions for reserved words and boundary characters
     * @param String $a The string to be quoted
     * @return String The quoted string
     */
    private static function quote_regex($a)
    {
        return preg_quote($a, '/');
    }

    /**
     * Helper function for building string output
     * @param String $string The string to be quoted
     * @return String The quoted string
     */
    private static function output($string)
    {
        if (self::is_cli()) {
            return $string . PHP_EOL;
        } else {
            $string = trim($string);
            if (!self::$use_pre) {
                return $string;
            }

            return $string;
        }
    }

    private static function is_cli()
    {
        if (isset(self::$cli)) {
            return self::$cli;
        } else {
            return php_sapi_name() === 'cli';
        }
    }

}

/**
 * Class array2php
 */
class JBDump_array2php
{

    const LE  = PHP_EOL;
    const TAB = "    ";

    /**
     * @param      $array
     * @param null $varName
     * @return string
     */
    public static function toString($array, $varName = null, $shift = 0)
    {
        $self     = new self();
        $rendered = $self->_render($array, 0);

        if ($shift > 0) {
            $rendered = explode(self::LE, $rendered);

            foreach ($rendered as $key => $line) {
                $rendered[$key] = $self->_getIndent($shift) . $line;
            }

            $rendered[0] = ltrim($rendered[0]);
            $rendered    = implode(self::LE, $rendered);
        }

        if ($varName) {
            return PHP_EOL . $self->_getIndent($shift) . "\$" . $varName . ' = ' . $rendered . ";" . PHP_EOL . " " . self::TAB;
        }

        return $rendered;
    }

    /**
     * @param     $array
     * @param int $depth
     * @return string
     */
    protected function _render($array, $depth = 0)
    {
        $isObject = false;

        if ($depth >= 10) {
            return 'null /* MAX DEEP REACHED! */';
        }

        if (is_object($array)) {
            $isObject = get_class($array);
            $array    = (array)$array;
        }

        if (!is_array($array)) {
            return 'null /* undefined var */';
        }

        if (empty($array)) {
            return $isObject ? '(object)array( /* Object: "' . $isObject . '" */)' : 'array()';
        }

        $string = 'array( ' . self::LE;
        if ($isObject) {
            $string = '(object)array( ' . self::LE . $this->_getIndent($depth + 1) . '/* Object: "' . $isObject . '" */ ' . self::LE;
        }

        $depth++;
        foreach ($array as $key => $val) {
            $string .= $this->_getIndent($depth) . $this->_quoteWrap($key) . ' => ';

            if (is_array($val) || is_object($val)) {
                $string .= $this->_render($val, $depth) . ',' . self::LE;
            } else {
                $string .= $this->_quoteWrap($val) . ',' . self::LE;
            }
        }

        $depth--;
        $string .= $this->_getIndent($depth) . ')';

        return $string;
    }

    /**
     * @param $depth
     * @return string
     */
    protected function _getIndent($depth)
    {
        return str_repeat(self::TAB, $depth);
    }

    /**
     * @param $var
     * @return string
     */
    protected function _quoteWrap($var)
    {
        $type = strtolower(gettype($var));

        switch ($type) {
            case 'string':
                return "'" . str_replace("'", "\\'", $var) . "'";

            case 'null':
                return "null";

            case 'boolean':
                return $var ? 'TRUE' : 'FALSE';

            case 'object':
                return '"{ Object: ' . get_class($var) . ' }"';

            //TODO: handle other variable types.. ( objects? )
            case 'integer':
            case 'double':
            default :
                return $var;
        }
    }
}


/**
 * Alias for JBDump::i()->dump($var) with additions params
 * @param   mixed  $var   Variable
 * @param   string $name  Variable name
 * @param   bool   $isDie Die after dump
 * @return  JBDump
 */
function jbdump($var = 'JBDump::variable is no set', $isDie = true, $name = '...')
{
    $_this = JBDump::i();

    if ($var != 'JBDump::variable is no set') {

        if ($_this->isDebug()) {
            $_this->dump($var, $name);
            $isDie && die('JBDump_auto_die');
        }

    }

    return $_this;
}
