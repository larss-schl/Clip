<?php
/**
 * Clip
 *
 * @copyright  (c) Clip Team
 * @link       http://code.zikula.org/clip/
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package    Clip
 * @subpackage View_Plugins
 */

/**
 * Relation form plugin.
 */
function smarty_function_clip_form_relation($params, &$view) {
    return $view->registerPlugin('ClipFormRelation', $params);
}

class ClipFormRelation extends Zikula_Form_Plugin_TextInput
{
    public $relinfo;

    public $numitems;
    public $maxitems;
    public $minchars;
    public $op;

    /**
     * Get filename for this plugin.
     *
     * @internal
     * @return string
     */
    function getFilename()
    {
        return __FILE__;
    }

    /**
     * Constructor.
     *
     * @param Zikula_Form_View $view    Reference to Zikula_Form_View object.
     * @param array     &$params Parameters passed from the Smarty plugin function.
     */
    function __construct($view, &$params)
    {
        // input relation data
        if (isset($params['relation'])) {
            $this->relinfo = $params['relation'];
        }

        if (!is_null($this->relinfo)) {
            // detects single or multiple relation
            $this->relinfo['single'] = $this->relinfo['own'] ? ($this->relinfo['type']%2 == 0 ? true : false) : ($this->relinfo['type'] <= 1 ? true : false);

            // assign existing data
            $this->relinfo['data'] = array();
            $data = $view->_tpl_vars['pubdata'][$params['id']];
            if ($data) {
                if ($this->relinfo['single']) {
                    $this->relinfo['data'][$data['id']] = $data['core_title'];
                } else {
                    foreach ($data as $r) {
                        $this->relinfo['data'][$r['id']] = $r['core_title'];
                    }
                }
            }
        }

        $params['textMode'] = 'hidden';

        parent::__construct($view, $params);
    }

    /**
     * Create event handler.
     *
     * @param Zikula_Form_View $view    Reference to Zikula_Form_View object.
     * @param array     &$params Parameters passed from the Smarty plugin function.
     *
     * @see    Zikula_Form_Plugin
     * @return void
     */
    function create($view, &$params)
    {
        parent::create($view, $params);

        $this->numitems = (isset($params['numitems']) && is_int($params['numitems'])) ? abs($params['numitems']) : 30;
        $this->maxitems = (isset($params['maxitems']) && is_int($params['maxitems'])) ? abs($params['maxitems']) : 20;
        $this->minchars = (isset($params['minchars']) && is_int($params['minchars'])) ? abs($params['minchars']) : 2;

        $this->op = (isset($params['op']) && in_array($params['op'], array('search', 'likefirst', 'like'))) ? $params['op'] : 'search';
    }

    /**
     * Post-initialise hook.
     *
     * @return void
     */
    public function postInitialize()
    {
    }

    /**
     * Render event handler.
     *
     * @param Zikula_Form_View $view Reference to Zikula_Form_View object.
     *
     * @return string The rendered output
     */
    function render($view)
    {
        $result = parent::render($view);

        // build the autocompleter setup
        PageUtil::addVar('javascript', 'prototype');
        PageUtil::addVar('javascript', 'modules/Clip/javascript/Zikula.Autocompleter.js');
        $script =
        "<script type=\"text/javascript\">\n// <![CDATA[\n".'
            function clip_enable_'.$this->id.'() {
                var_auto_'.$this->id.' = new Zikula.Autocompleter(\''.$this->id.'\', \''.$this->id.'_div\',
                                                 {
                                                  fetchFile: Zikula.Config.baseURL+\'ajax.php\',
                                                  parameters: {
                                                    module: "Clip",
                                                    func: "autocomplete",
                                                    tid: '.$this->relinfo['tid'].',
                                                    itemsperpage: '.$this->numitems.',
                                                    op: "'.$this->op.'"
                                                  },
                                                  minchars: '.$this->minchars.',
                                                  maxresults: '.$this->numitems.',
                                                  maxItems: '.($this->relinfo['single'] ? 1 : $this->maxitems).'
                                                 });
            }
            Event.observe(window, \'load\', clip_enable_'.$this->id.', false);
        '."\n// ]]>\n</script>";
        PageUtil::addVar('rawtext', $script);

        // build the autocompleter output
        $typeDataHtml = '
        <div id="'.$this->id.'_div" class="z-auto-container">
            <div class="z-auto-default">'.
                (!empty($this->relinfo['descr']) ? $this->relinfo['descr'] : $this->_fn('Type the title of the related publication', 'Type the titles of the related publications', $this->relinfo['single'] ? 1 : 2, array())).
            '</div>
            <ul class="z-auto-feed">
                ';
        foreach ($this->relinfo['data'] as $value => $title) {
            $typeDataHtml .= '<li value="'.$value.'">'.$title.'</li>';
        }
        $typeDataHtml .= '
            </ul>
        </div>';

        return $result . $typeDataHtml;
    }

    /**
     * Saves value in data object.
     *
     * Called by the render when doing $view->getValues()
     * Uses the group parameter to decide where to store data.
     *
     * @param Zikula_Form_View $view  Reference to Zikula_Form_View object.
     * @param array     &$data Data object.
     *
     * @return void
     */
    function saveValue($view, &$data)
    {
        if ($this->dataBased) {
            $value = $this->parseValue($view, $this->text);

            $ref = $this->relinfo['single'] ? array($value) : explode(':', $value);

            if ($this->group == null) {
                $data[$this->dataField] = $ref;
            } else {
                if (!array_key_exists($this->group, $data)) {
                    $data[$this->group] = array();
                }
                $data[$this->group][$this->dataField] = $ref;
            }
        }
    }
}
