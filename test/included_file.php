<?php
/**
 * @package     JBDump test
 * @version     1.2.2
 * @author      admin@joomla-book.ru
 * @link        http://joomla-book.ru/
 * @copyright   Copyright (c) 2009-2011 Joomla-book.ru
 * @license     GNU General Public License version 2 or later; see LICENSE
 */


/**
 * Simple function with backtrace
 */
function simpleFunction($arg1, $arg2 = false)
{
    JBDump::trace(1);
}


/**
 * Simple Closure Function
 */
$simpleClosureFunction = false;

if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
    include 'included_closure.php';
}
 
 
/**
 * Simple interface
 */
interface simpleInterface
{
    const b = 'Interface constant';

    public function method($name, $var = '3.14');
}

/**
 * Simple empty interface
 */
interface simpleEmptyInterface
{
}

/**
 * simpleRootObject
 */
class simpleRootObject implements simpleInterface
{
    public function method($name, $var = '3.14')
    {

    }
}

/**
 * Simple Parent Object
 */
abstract class simpleParentObject extends simpleRootObject implements simpleEmptyInterface
{

    /**
     * Protected parent method
     */
    protected function protectedParentMethod()
    {
        return true;
    }


    /**
     * Simple parent method
     */
    function parentMethod()
    {
        return true;
    }

    /**
     * Simple method
     */
    function method($name, $var = '3.14')
    {
        return true;
    }

    /**
     * Public parent absract method
     */
    public abstract function publicMethod();

}

/**
 * Simple class
 */
class simpleObject extends simpleParentObject
{

    const SIMPLE_CONST = 3.14;

    var $simpleVar = '';
    static $staticVar = '';
    public $publicVar = '';
    private $_privateVar = '';
    private $_protectedVar = '';

    /**
     * Public method
     */
    public function publicMethod()
    {
        return true;
    }

    /**
     * Private method
     */
    private function privateMethod()
    {
        return true;
    }

    /**
     * Protected method
     */
    protected function protectedMethod()
    {
        return true;
    }

    /**
     * Static public method
     */
    static public function staticPublicMethod()
    {
        return true;
    }

    /**
     * Static private method
     */
    static private function staticPrivateMethod()
    {
        return true;
    }

    /**
     * Simple method
     */
    function method($name, $var = '3.14')
    {
        return true;
    }

    /**
     * Check trace
     */
    public function checkTrace()
    {
        JBDump::trace(1);
        simpleFunction('simple string');
    }

    /**
     * Constructor
     */
    function __construct()
    {
        $this->simpleVar     = 'simple var';
        $this->publicVar     = 'public var';
        $this->_privateVar   = 'private var';
        $this->_protectedVar = 'protected var';
        self::$staticVar     = 'static var';
    }

    /**
     * Destructor
     */
    function __destruct()
    {
        unset($this->simpleVar);
    }

    /**
     * Exception test
     */
    function getException($a = '123')
    {
        $b = '456';
        $c = '789';
        throw new Exception('Uncaught Exception');
    }

    /**
     * Test method func args
     */
    function testFuncArgs($a, $b, $c = 123456)
    {
        jbdump::args();
    }
}

/**
 * function to test the error handling
 */
function scale_by_log($vect, $scale)
{
    if (!is_numeric($scale) || $scale <= 0) {
        trigger_error("log(x) for x <= 0 is undefined, you used: scale = $scale", E_USER_ERROR);
    }

    if (!is_array($vect)) {
        trigger_error("Incorrect input vector, array of values expected", E_USER_WARNING);
        return null;
    }

    $temp = array();
    foreach ($vect as $pos => $value) {
        if (!is_numeric($value)) {
            trigger_error("Value at position $pos is not a number, using 0 (zero)", E_USER_NOTICE);
            $value = 0;
        }
        $temp[$pos] = log($scale) * $value;
    }

    return $temp;
}

function testFuncArgs($a, $b, $c = 123456)
{
    jbdump::args();
}


// session start
session_start();

// user defined constants
define('CONSTANT_1', true);
define('CONSTANT_2', 'text');

// vars
$stdClass           = new stdClass();
$stdClass->property = 'Property value';
$jsonData           = '{"J":5,"0":"N"}';

// simple complex va
$var = array(
    'null'          => NULL,
    'bool'          => TRUE,
    'integer'       => 10,
    'float'         => 100.500,
    'string'        => '10 123 34',
    'longString'    => file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'testfile.txt'),
    'stdClass'      => $stdClass,
    'simpleObject'  => new simpleObject(),
    'json'          => $jsonData,
    'function'      => $simpleClosureFunction,
);

// nested vars
$var['var'] = $var;


if (!class_exists('JBDump')) {
    include_once ('../class.jbdump.php');
}

jbdump::i(array(
    'root' => realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..'),
    'profiler' => array(
        'render'    => 28,
        'showEnd'   => 1,
    ),    
));