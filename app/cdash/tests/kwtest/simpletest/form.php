<?php

/**
 *  Base include file for SimpleTest.
 *
 * @version    $Id$
 */

/**#@+
 * include SimpleTest files
 */
require_once dirname(__FILE__) . '/tag.php';
require_once dirname(__FILE__) . '/encoding.php';
require_once dirname(__FILE__) . '/selector.php';
/**#@-*/

/**
 *    Form tag class to hold widget values.
 */
class SimpleForm
{
    private $method;
    private $action;
    private $encoding;
    private $default_target;
    private $id;
    private $buttons;
    private $images;
    private $widgets;
    private $radios;
    private $checkboxes;

    /**
     *    Starts with no held controls/widgets.
     *
     * @param SimpleTag $tag form tag to read
     * @param SimplePage $page holding page
     */
    public function __construct($tag, $page)
    {
        $this->method = $tag->getAttribute('method');
        $this->action = $this->createAction($tag->getAttribute('action'), $page);
        $this->encoding = $this->setEncodingClass($tag);
        $this->default_target = false;
        $this->id = $tag->getAttribute('id');
        $this->buttons = [];
        $this->images = [];
        $this->widgets = [];
        $this->radios = [];
        $this->checkboxes = [];
    }

    /**
     *    Creates the request packet to be sent by the form.
     *
     * @param SimpleTag $tag form tag to read
     *
     * @return string packet class
     */
    protected function setEncodingClass($tag)
    {
        if (strtolower($tag->getAttribute('method')) == 'post') {
            if (strtolower($tag->getAttribute('enctype')) == 'multipart/form-data') {
                return 'SimpleMultipartEncoding';
            }
            return 'SimplePostEncoding';
        }
        return 'SimpleGetEncoding';
    }

    /**
     *    Sets the frame target within a frameset.
     *
     * @param string $frame name of frame
     */
    public function setDefaultTarget($frame)
    {
        $this->default_target = $frame;
    }

    /**
     *    Accessor for method of form submission.
     *
     * @return string either get or post
     */
    public function getMethod()
    {
        return $this->method ? strtolower($this->method) : 'get';
    }

    /**
     *    Combined action attribute with current location
     *    to get an absolute form target.
     *
     * @param string $action action attribute from form tag
     *
     * @return SimpleUrl absolute form target
     */
    protected function createAction($action, $page)
    {
        if (($action === '') || ($action === false)) {
            return $page->expandUrl($page->getUrl());
        }
        return $page->expandUrl(new SimpleUrl($action));
    }

    /**
     *    Absolute URL of the target.
     *
     * @return SimpleUrl URL target
     */
    public function getAction()
    {
        $url = $this->action;
        if ($this->default_target && !$url->getTarget()) {
            $url->setTarget($this->default_target);
        }
        if ($this->getMethod() == 'get') {
            $url->clearRequest();
        }
        return $url;
    }

    /**
     *    Creates the encoding for the current values in the
     *    form.
     *
     * @return SimpleFormEncoding request to submit
     */
    protected function encode()
    {
        $class = $this->encoding;
        $encoding = new $class();
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            $this->widgets[$i]->write($encoding);
        }
        return $encoding;
    }

    /**
     *    ID field of form for unique identification.
     *
     * @return string unique tag ID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *    Adds a tag contents to the form.
     *
     * @param SimpleWidget $tag input tag to add
     */
    public function addWidget($tag)
    {
        if (strtolower($tag->getAttribute('type')) == 'submit') {
            $this->buttons[] = $tag;
        } elseif (strtolower($tag->getAttribute('type')) == 'image') {
            $this->images[] = $tag;
        } elseif ($tag->getName()) {
            $this->setWidget($tag);
        }
    }

    /**
     *    Sets the widget into the form, grouping radio
     *    buttons if any.
     *
     * @param SimpleWidget $tag incoming form control
     */
    protected function setWidget($tag)
    {
        if (strtolower($tag->getAttribute('type')) == 'radio') {
            $this->addRadioButton($tag);
        } elseif (strtolower($tag->getAttribute('type')) == 'checkbox') {
            $this->addCheckbox($tag);
        } else {
            $this->widgets[] = &$tag;
        }
    }

    /**
     *    Adds a radio button, building a group if necessary.
     *
     * @param SimpleRadioButtonTag $tag incoming form control
     */
    protected function addRadioButton($tag)
    {
        if (!isset($this->radios[$tag->getName()])) {
            $this->widgets[] = new SimpleRadioGroup();
            $this->radios[$tag->getName()] = count($this->widgets) - 1;
        }
        $this->widgets[$this->radios[$tag->getName()]]->addWidget($tag);
    }

    /**
     *    Adds a checkbox, making it a group on a repeated name.
     *
     * @param SimpleCheckboxTag $tag incoming form control
     */
    protected function addCheckbox($tag)
    {
        if (!isset($this->checkboxes[$tag->getName()])) {
            $this->widgets[] = $tag;
            $this->checkboxes[$tag->getName()] = count($this->widgets) - 1;
        } else {
            $index = $this->checkboxes[$tag->getName()];
            if (!SimpleTestCompatibility::isA($this->widgets[$index], 'SimpleCheckboxGroup')) {
                $previous = $this->widgets[$index];
                $this->widgets[$index] = new SimpleCheckboxGroup();
                $this->widgets[$index]->addWidget($previous);
            }
            $this->widgets[$index]->addWidget($tag);
        }
    }

    /**
     *    Extracts current value from form.
     *
     * @param SimpleSelector $selector criteria to apply
     *
     * @return string/array              Value(s) as string or null
     *                                      if not set
     */
    public function getValue($selector)
    {
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($selector->isMatch($this->widgets[$i])) {
                return $this->widgets[$i]->getValue();
            }
        }
        foreach ($this->buttons as $button) {
            if ($selector->isMatch($button)) {
                return $button->getValue();
            }
        }
        return;
    }

    /**
     *    Sets a widget value within the form.
     *
     * @param SimpleSelector $selector criteria to apply
     * @param string $value value to input into the widget
     *
     * @return bool True if value is legal, false
     *              otherwise. If the field is not
     *              present, nothing will be set.
     */
    public function setField($selector, $value, $position = false)
    {
        $success = false;
        $_position = 0;
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($selector->isMatch($this->widgets[$i])) {
                $_position++;
                if ($position === false or $_position === (int) $position) {
                    if ($this->widgets[$i]->setValue($value)) {
                        $success = true;
                    }
                }
            }
        }
        return $success;
    }

    /**
     *    Used by the page object to set widgets labels to
     *    external label tags.
     *
     * @param SimpleSelector $selector criteria to apply
     */
    public function attachLabelBySelector($selector, $label)
    {
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($selector->isMatch($this->widgets[$i])) {
                if (method_exists($this->widgets[$i], 'setLabel')) {
                    $this->widgets[$i]->setLabel($label);
                    return;
                }
            }
        }
    }

    /**
     *    Test to see if a form has a submit button.
     *
     * @param SimpleSelector $selector criteria to apply
     *
     * @return bool true if present
     */
    public function hasSubmit($selector)
    {
        foreach ($this->buttons as $button) {
            if ($selector->isMatch($button)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Test to see if a form has an image control.
     *
     * @param SimpleSelector $selector criteria to apply
     *
     * @return bool true if present
     */
    public function hasImage($selector)
    {
        foreach ($this->images as $image) {
            if ($selector->isMatch($image)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Gets the submit values for a selected button.
     *
     * @param SimpleSelector $selector criteria to apply
     * @param hash $additional additional data for the form
     *
     * @return SimpleEncoding submitted values or false
     *                        if there is no such button
     *                        in the form
     */
    public function submitButton($selector, $additional = false)
    {
        $additional = $additional ? $additional : [];
        foreach ($this->buttons as $button) {
            if ($selector->isMatch($button)) {
                $encoding = $this->encode();
                $button->write($encoding);
                if ($additional) {
                    $encoding->merge($additional);
                }
                return $encoding;
            }
        }
        return false;
    }

    /**
     *    Gets the submit values for an image.
     *
     * @param SimpleSelector $selector criteria to apply
     * @param int $x X-coordinate of click
     * @param int $y Y-coordinate of click
     * @param hash $additional additional data for the form
     *
     * @return SimpleEncoding submitted values or false
     *                        if there is no such button in the
     *                        form
     */
    public function submitImage($selector, $x, $y, $additional = false)
    {
        $additional = $additional ? $additional : [];
        foreach ($this->images as $image) {
            if ($selector->isMatch($image)) {
                $encoding = $this->encode();
                $image->write($encoding, $x, $y);
                if ($additional) {
                    $encoding->merge($additional);
                }
                return $encoding;
            }
        }
        return false;
    }

    /**
     *    Simply submits the form without the submit button
     *    value. Used when there is only one button or it
     *    is unimportant.
     *
     * @return hash submitted values
     */
    public function submit($additional = false)
    {
        $encoding = $this->encode();
        if ($additional) {
            $encoding->merge($additional);
        }
        return $encoding;
    }
}
