<?php
/**
 * Library for dump variables and profiling PHP code
 * The idea and the look was taken from http://krumo.sourceforge.net/
 * PHP version 5.3 or higher
 * @package     JBDump
 * @version     1.2.11
 * @author      admin@joomla-book.ru
 * @link        http://joomla-book.ru/
 * @copyright   Copyright (c) 2009-2011 Joomla-book.ru
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.event.helper');

if (!class_exists('jbdump')) {
    require_once(dirname(__FILE__) . DS . 'class.jbdump.php');
}

class plgSystemJBDump extends JPlugin
{

    /**
     * Plugin init
     */
    function plgSystemJBDump($subject, $params)
    {
        parent::__construct($subject, $params);
    }


    /**
     * JBDump init
     */
    function onAfterInitialise()
    {

        $logPath = $this->params->get('logPath', JPATH_ROOT . DS . 'logs');
        if (empty($logPath)) {
            $logPath = JPATH_ROOT . DS . 'logs';
        }

        $logFile = $this->params->get('logFile', false);
        if (empty($logFile)) {
            $logFile = false;
        }

        // init and configuration JBDump library
        $params = array(

            'root'          => JPATH_SITE,

            // file logger
            'logPath'       => $logPath,
            'logFile'       => $logFile,
            'serialize'     => $this->params->get('serialize', 'print_r'),
            'logFormat'     => "{DATETIME}\t{CLIENT_IP}\t\t{FILE}\t\t{NAME}\t\t{TEXT}",

            // profiler
            'autoProfile'   => (int)$this->params->get('autoProfile', true),
            'profileToFile' => (int)$this->params->get('profileToFile', false),

            // sorting (ASC)
            'sort'          => array(
                'array'     => (int)$this->params->get('sort_array', false),
                'object'    => (int)$this->params->get('sort_object', true),
                'methods'   => (int)$this->params->get('sort_methods', true),
            ),

            // handlers
            'handler'       => array(
                'error'     => (int)$this->params->get('handler_error', true),
                'exception' => (int)$this->params->get('handler_exception', true),
                'context'   => (int)$this->params->get('handler_context', false),
            ),

            // personal dump
            'ip'            => $this->params->get('ip', false),
            'requestParam'  => $this->params->get('requestParam', false),
            'requestValue'  => $this->params->get('requestValue', false),

            // others
            'lite_mode'     => (int)$this->params->get('lite_mode', false),
            'stringLength'  => $this->params->get('stringLength', 50),
            'maxDepth'      => $this->params->get('maxDepth', 3),
            'showMethods'   => (int)$this->params->get('showMethods', true),
            'allToLog'      => (int)$this->params->get('allToLog', false),
            'showArgs'      => (int)$this->params->get('showMethods', false),
        );

        // init jbdump
        JBDump::i('jbdump', $params);
    }

}

// usability methods
if (!function_exists('d')) {
    function d($var = 'JBDump::variable no set', $isDie = true, $name = '...')
    {
        $_this = JBDump::i();

        if ($var !== 'JBDump::variable no set') {
            if ($_this->isDebug()) {

                $params = array('trace' => debug_backtrace());
                $_this->dump($var, $name, $params);

                if ($isDie) {
                    die('JBDump_die');
                }
            }
        }

        return $_this;
    }

}

if (!function_exists('l')) {
    function l($entry, $mark = '...')
    {
        $params = array('trace' => debug_backtrace());
        return JBDump::log($entry, $mark, $params);
    }
}

if (!function_exists('m')) {
    function m($text)
    {
        return JBDump::mark($text);
    }
}
