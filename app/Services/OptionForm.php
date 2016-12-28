<?php

namespace App\Services;

use Option;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OptionForm
{
    public $id;
    public $title;

    protected $hint;
    protected $items;

    protected $success = false;

    protected $messages = [];

    protected $alwaysCallback = null;

    public function __construct($id, $title)
    {
        $this->id    = $id;
        $this->title = $title;
    }

    public function text($id, $name, $value = null)
    {
        return $this->addItem('text', $id, $name)->set('value', $value);
    }

    public function checkbox($id, $name, $label, $checked = null)
    {
        $checkbox = $this->addItem('checkbox', $id, $name)->set('label', $label);

        return $checkbox;
    }

    public function select($id, $name, $callback)
    {
        $item = $this->addItem('select', $id, $name);

        $select = new OptionFormSelect($id);

        call_user_func($callback, $select);

        $item->set('view', $select);

        return $select;
    }

    public function textarea($id, $name, $callback)
    {
        $item = $this->addItem('textarea', $id, $name);

        $textarea = new OptionFormTextarea($id);

        call_user_func($callback, $textarea);

        $item->set('view', $textarea);

        return $textarea;
    }

    public function group($id, $name, $callback)
    {
        $item = $this->addItem('group', $id, $name);

        $group = new OptionFormGroup($id);

        call_user_func($callback, $group);

        $item->set('view', $group);

        return $item;
    }

    public function addItem($type, $id, $name)
    {
        $item = new OptionFormItem($id, $name, $type);

        $this->items[] = $item;

        return $item;
    }

    public function hint($hint_content)
    {
        $this->hint = view('vendor.option-form.hint')->with('hint', $hint_content)->render();

        return $this;
    }

    public function addMessage($msg, $type = "info")
    {
        $this->messages[] = "<div class='callout callout-$type'>$msg</div>";
    }

    public function handle($callback = null)
    {
        if (Arr::get($_POST, 'option') == $this->id) {
            if (!is_null($callback)) {
                call_user_func($callback, $this);
            }

            foreach ($this->items as $item) {
                if ($item->type == "checkbox" && !isset($_POST[$item->id])) {
                    // preset value for checkboxes which are not checked
                    $_POST[$item->id] = "0";
                }

                if (Str::is('*[*]', $item->id))
                    continue;

                if ($_POST[$item->id] != option($item->id, null, false)) {
                    Option::set($item->id, $_POST[$item->id]);
                }
            }

            session()->flash($this->id.'.status', 'success');
        }

        return $this;
    }

    public function always($callback)
    {
        $this->alwaysCallback = $callback;

        return $this;
    }

    public function render()
    {
        if (!is_null($this->alwaysCallback)) {
            call_user_func($this->alwaysCallback, $this);
        }

        foreach ($this->items as $item) {
            $id = $item->id;

            if (!$value = $item->get('value')) {
                preg_match('/(.*)\[(.*)\]/', $id, $matches);

                if (isset($matches[2])) {
                    $option = @unserialize(option($matches[1]));

                    $value = Arr::get($option, $matches[2]);
                } else {
                    $value = option($item->id);
                }
            }

            switch ($item->type) {
                case 'text':
                    $view = view('vendor.option-form.text')->with(compact('id', 'value'));
                    break;

                case 'checkbox':
                    $view = view('vendor.option-form.checkbox')->with([
                        'id'      => $id,
                        'label'   => $item->get('label'),
                        'checked' => (bool) $value
                    ]);
                    break;

                case 'select':
                case 'textarea':
                case 'group':
                    $view = $item->get('view')->render();
                    break;
            }

            $item->setContent($view->render());
        }

        return view('vendor.option-form.main')->with(get_object_vars($this))->render();
    }
}

class OptionFormItem
{
    public $id;
    public $type;
    public $title;

    protected $data;

    protected $hint;
    protected $content;

    public function __construct($id, $title, $type = "")
    {
        $this->id    = $id;
        $this->type  = $type;
        $this->title = $title;
    }

    public function hint($hint_content)
    {
        $this->hint = view('vendor.option-form.hint')->with('hint', $hint_content)->render();

        return $this;
    }

    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function get($key)
    {
        return Arr::get($this->data, $key);
    }

    public function render()
    {
        return view('vendor.option-form.item')->with([
            'title' => $this->title,
            'content' => $this->content,
            'hint' => $this->hint
        ]);
    }

}

class OptionFormSelect
{
    protected $id;

    protected $items;

    protected $selected = null;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function add($id, $name)
    {
        $this->items[] = [$id, $name];
    }

    public function setSelected($id)
    {
        $this->selected = $id;
    }

    public function render()
    {
        if (is_null($this->selected)) {
            $this->selected = option($this->id);
        }

        return view('vendor.option-form.select')->with([
            'id' => $this->id,
            'items' => $this->items,
            'selected' => $this->selected
        ]);
    }
}

class OptionFormTextarea
{
    protected $id;

    protected $value;

    protected $rows = 3;

    protected $description = "";

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function setContent($content)
    {
        $this->value = $content;
    }

    public function setRows($rows)
    {
        $this->rows = $rows;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function render()
    {
        if (is_null($this->value)) {
            $this->value = option($this->id);
        }

        return view('vendor.option-form.textarea')->with([
            'rows' => $this->rows,
            'id' => $this->id,
            'value' => $this->value,
            'description' => $this->description
        ]);
    }
}

class OptionFormGroup
{
    protected $id;

    protected $items = [];

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function text($id, $value = null)
    {
        $this->items[] = ['type' => 'text', 'id' => $id, 'value' => $value];
    }

    public function addon($value)
    {
        $this->items[] = ['type' => 'addon', 'id' => null, 'value' => $value];
    }

    public function render()
    {
        $rendered = [];

        foreach ($this->items as $item) {
            if ($item['id'] && is_null($item['value'])) {
                $item['value'] = option($item['id'], null, false);
            }

            $rendered[] = view('vendor.option-form.'.$item['type'])->withId($item['id'])->withValue($item['value']);
        }

        return view('vendor.option-form.group')->with('items', $rendered);
    }
}
