<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 * Include required SimpleTest files
 */
require_once dirname(__FILE__) . '/invoker.php';
require_once dirname(__FILE__) . '/expectation.php';
/**#@-*/

/**
 *    Extension that traps exceptions and turns them into
 *    an error message. PHP5 only.
 */
class SimpleExceptionTrappingInvoker extends SimpleInvokerDecorator
{
    /**
     *    Stores the invoker to be wrapped.
     *
     * @param SimpleInvoker $invoker test method runner
     */
    public function __construct($invoker)
    {
        parent::__construct($invoker);
    }

    /**
     *    Invokes a test method whilst trapping expected
     *    exceptions. Any left over unthrown exceptions
     *    are then reported as failures.
     *
     * @param string $method test method to call
     */
    public function invoke($method)
    {
        $trap = SimpleTest::getContext()->get('SimpleExceptionTrap');
        $trap->clear();
        try {
            $has_thrown = false;
            parent::invoke($method);
        } catch (Throwable $exception) {
            $has_thrown = true;
            if (!$trap->isExpected($this->getTestCase(), $exception)) {
                $this->getTestCase()->exception($exception);
            }
            $trap->clear();
        }
        if ($message = $trap->getOutstanding()) {
            $this->getTestCase()->fail($message);
        }
        if ($has_thrown) {
            try {
                parent::getTestCase()->tearDown();
            } catch (Exception $e) {
            }
        }
    }
}

/**
 *    Tests exceptions either by type or the exact
 *    exception. This could be improved to accept
 *    a pattern expectation to test the error
 *    message, but that will have to come later.
 */
class ExceptionExpectation extends SimpleExpectation
{
    private $expected;

    /**
     *    Sets up the conditions to test against.
     *    If the expected value is a string, then
     *    it will act as a test of the class name.
     *    An exception as the comparison will
     *    trigger an identical match. Writing this
     *    down now makes it look doubly dumb. I hope
     *    come up with a better scheme later.
     *
     * @param mixed $expected a class name or an actual
     *                        exception to compare with
     * @param string $message message to display
     */
    public function __construct($expected, $message = '%s')
    {
        $this->expected = $expected;
        parent::__construct($message);
    }

    /**
     *    Carry out the test.
     *
     * @param Exception $compare value to check
     *
     * @return bool true if matched
     */
    public function test($compare)
    {
        if (is_string($this->expected)) {
            return $compare instanceof $this->expected;
        }
        if (get_class($compare) != get_class($this->expected)) {
            return false;
        }
        return $compare->getMessage() == $this->expected->getMessage();
    }

    /**
     *    Create the message to display describing the test.
     *
     * @param Exception $compare exception to match
     *
     * @return string final message
     */
    public function testMessage($compare)
    {
        if (is_string($this->expected)) {
            return 'Exception [' . $this->describeException($compare) .
            '] should be type [' . $this->expected . ']';
        }
        return 'Exception [' . $this->describeException($compare) .
        '] should match [' .
        $this->describeException($this->expected) . ']';
    }

    /**
     *    Summary of an Exception object.
     *
     * @return string text description
     */
    protected function describeException($exception)
    {
        return get_class($exception) . ': ' . $exception->getMessage();
    }
}

/**
 *    Stores expected exceptions for when they
 *    get thrown. Saves the irritating try...catch
 *    block.
 */
class SimpleExceptionTrap
{
    private $expected;
    private $ignored;
    private $message;

    /**
     *    Clears down the queue ready for action.
     */
    public function __construct()
    {
        $this->clear();
    }

    /**
     *    Sets up an expectation of an exception.
     *    This has the effect of intercepting an
     *    exception that matches.
     *
     * @param SimpleExpectation $expected expected exception to match
     * @param string $message message to display
     */
    public function expectException($expected = false, $message = '%s')
    {
        $this->expected = $this->coerceToExpectation($expected);
        $this->message = $message;
    }

    /**
     *    Adds an exception to the ignore list. This is the list
     *    of exceptions that when thrown do not affect the test.
     *
     * @param SimpleExpectation $ignored exception to skip
     */
    public function ignoreException($ignored)
    {
        $this->ignored[] = $this->coerceToExpectation($ignored);
    }

    /**
     *    Compares the expected exception with any
     *    in the queue. Issues a pass or fail and
     *    returns the state of the test.
     *
     * @param SimpleTestCase $test test case to send messages to
     * @param Exception $exception exception to compare
     *
     * @return bool false on no match
     */
    public function isExpected($test, $exception)
    {
        if ($this->expected) {
            return $test->assert($this->expected, $exception, $this->message);
        }
        foreach ($this->ignored as $ignored) {
            if ($ignored->test($exception)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Turns an expected exception into a SimpleExpectation object.
     *
     * @param mixed $exception exception, expectation or
     *                         class name of exception
     *
     * @return SimpleExpectation expectation that will match the
     *                           exception
     */
    private function coerceToExpectation($exception)
    {
        if ($exception === false) {
            return new AnythingExpectation();
        }
        if (!SimpleExpectation::isExpectation($exception)) {
            return new ExceptionExpectation($exception);
        }
        return $exception;
    }

    /**
     *    Tests for any left over exception.
     *
     * @return string/false     The failure message or false if none
     */
    public function getOutstanding()
    {
        return sprintf($this->message, 'Failed to trap exception');
    }

    /**
     *    Discards the contents of the error queue.
     */
    public function clear()
    {
        $this->expected = false;
        $this->message = false;
        $this->ignored = [];
    }
}
