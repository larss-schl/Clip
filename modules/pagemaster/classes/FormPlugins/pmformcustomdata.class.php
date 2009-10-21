<?php
/**
 * PageMaster
 *
 * @copyright   (c) PageMaster Team
 * @link        http://code.zikula.org/pagemaster/
 * @license     GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @version     $ Id $
 * @package     Zikula_3rdParty_Modules
 * @subpackage  pagemaster
 */

require_once('system/pnForm/plugins/function.pnformtextinput.php');

class pmformcustomdata extends pnFormTextInput
{
    var $columnDef = 'X';
    var $title     = 'Custom Data';

    function getFilename()
    {
        return __FILE__;
    }

    function create(&$render, $params)
    {
        parent::create($render, $params);
        if (empty($this->text)) {
            $config = $this->parseConfig($render->pnFormEventHandler->pubfields[$this->inputName]['typedata'], 0);
            $defaultvalue = isset($config['configvars'][1]) ? $config['configvars'][1] : '';
            $this->text = ($defaultvalue != '~' ? $defaultvalue : '');
        }
    }

    function render(&$render)
    {
        $this->textMode = 'singleline';
        $render->assign($this->inputName, @unserialize($this->text));
        if (isset($render->pnFormEventHandler->pubfields[$this->inputName])) {
            $render->assign($this->inputName.'_typedata', $this->parseConfig($render->pnFormEventHandler->pubfields[$this->inputName]['typedata'], 0));
        }
        return parent::render($render);
    }

    static function postRead($data, $field)
    {
        if (!empty($data)) {
            $data = @unserialize($data);

            // if not a section or no items/config returns the data
            if (!isset($data['enabled']) || (isset($data['items']) && empty($data['items'])) || empty($field['typedata'])) {
                return $data;
            } elseif ($data['enabled'] == 'on') {
                // parse and save the configuration
                $field['typedata'] = $this->parseConfig($field['typedata'], 0);
                $ak = array_keys($data['items']);
                foreach ($ak as $key) {
                    if (!is_null($data['items'][$key]['value'])) {
                        $type = $data['items'][$key]['type'];
                        $call = $this->parseCall($field['typedata'][$type][2], $data['items'][$key]);
                        // execute the defined API call
                        $data['items'][$key]['data'] = pnModAPIFunc($call[0], $call[1], $call[2], $call[3]);
                    } else {
                        $data['items'][$key]['data'] = '';
                    }
                }
                return $data;

            } else {
                return array('enabled' => 'off');
            }
        } else {
            return NULL;
        }
    }

    function decode(&$render)
    {
        // Do not read new value if readonly (evil submiter might have forged it)
        if (!$this->readOnly)
        {
            $this->text = FormUtil::getPassedValue($this->inputName, null, 'POST');

            if (is_null($this->text) || empty($this->text)) {
                $config = $this->parseConfig($render->pnFormEventHandler->pubfields[$this->inputName]['typedata'], 0);
                $this->text = $config['configvars'][1];
                return;
            }

            $ak = array_keys($this->text);
            // loop the text cleaning the text fields
            foreach ($ak as $key) {
                if (is_array($this->text[$key])) {
                    continue;
                }

                if (get_magic_quotes_gpc()) {
                    $this->text[$key] = stripslashes($this->text[$key]);
                }

                // Make sure newlines are returned as "\n" - always.
                $this->text[$key] = str_replace("\r\n", "\n", $this->text[$key]);
                $this->text[$key] = str_replace("\r", "\n", $this->text[$key]);

                $this->text[$key] = trim($this->text[$key]);
            }

            $tmpvar    = null;
            $tmparray  = array();
            $dataarray = array();
            // loop the text again to explode the javascript compressed array-keys
            foreach ($ak as $key) {
                $i = 0;
                $tmparray = explode('.', str_replace("'", '', $key));
                if (count($tmparray) == 1) {
                    $dataarray[$tmparray[$i]] = $this->text[$key];
                } else {
                    if (!isset($dataarray[$tmparray[$i]])) {
                        $dataarray[$tmparray[$i]] = array();
                    }
                    $tmpvar = &$dataarray[$tmparray[$i]];
                    for ($i = 1; $i < count($tmparray); $i++) {
                        if ($i == count($tmparray)-1) {
                            $tmpvar[$tmparray[$i]] = $this->text[$key];
                        } else {
                            if (!isset($tmpvar[$tmparray[$i]])) {
                                $tmpvar[$tmparray[$i]] = array();
                            }
                            $tmpvar = &$tmpvar[$tmparray[$i]];
                        }
                    }
                }
            }

            $this->text = @serialize($dataarray);
        }
    }

    static function getSaveTypeDataFunc($field)
    {
        $saveTypeDataFunc = 'function saveTypeData()
                             {
                                 var elements = $$("#pmcustomdata input")
                                 var result = Form.serializeElements(elements, true)
                                 debug = result
                                 var compressed = new Array()

                                 if (Object.isArray(result[\'itemname[]\']) == false) {
                                     if (Object.isString(result[\'itemname[]\']) && result[\'itemname[]\'] != "") {
                                         compressed.push(result[\'itemname[]\']+\'|\'+result[\'itemdisplay[]\']+\'|\'+result[\'itemapi[]\']+\'|\'+result[\'itemajax[]\'])
                                     } else {
                                         compressed.push(\'\')
                                     }
                                 } else {
                                     var data = new Array()
                                     var max = result[\'itemname[]\'].length
                                     for (var i = 0; i < max; ++i) {
                                         data.clear()
                                         data.push(result[\'itemname[]\'].shift())
                                         data.push(result[\'itemdisplay[]\'].shift())
                                         data.push(result[\'itemapi[]\'].shift())
                                         data.push(result[\'itemajax[]\'].shift())
                                         data.each(function(item, index) {
                                             if (item == \'\') data[index] = \'~\'
                                         })
                                         compressed.push(data.join(\'|\'))
                                     }
                                 }

                                 var configvars = new Array(\'configvars\')
                                 if ($F(\'pmplugin_defaultdata\') != \'\') {
                                     configvars.push($F(\'pmplugin_defaultdata\'))
                                 } else {
                                     configvars.push(\'~\')
                                 }
                                 compressed.push(configvars.join(\'|\')+\'|\')

                                 $(\'typedata\').value = compressed.join(\'||\')
                                 closeTypeData()
                             }';

        return $saveTypeDataFunc;
    }

    static function getTypeHtml($field, $render)
    {
        PageUtil::addVar('javascript', 'modules/pagemaster/pnjavascript/Zikula.itemlist.js');

        // parse the data
        if (isset($render->_tpl_vars['typedata'])) {
            $vars = explode('||', $render->_tpl_vars['typedata']);
        } else {
            $vars = array();
        }

        if (is_array($vars) && !empty($vars)) {
            // extract and clean the config vars
            $configvars = explode('|', array_pop($vars));
            foreach ($configvars as $key => $var) {
                if ($var == '~') {
                    $configvars[$key] = '';
                }
            }
            // extract the item types
            foreach ($vars as $key => $var) {
                $vars[$key] = explode('|', $var);
            }
        } else {
            $configvars = array('','');
            $vars = array();
        }

        $html = '<div class="pn-formrow">
                 <div>
                   <label for="pmplugin_defaultdata">'._DEFAULT.':</label> <input type="text" id="pmplugin_defaultdata" name="pmplugin_defaultdata" value="'.str_replace('"', '&quot;', $configvars[1]).'" />
                 </div>
                 <div class="newitemlistdiv">
                   <a onclick="javascript:itemlist_pmcustomdata.appenditem();" href="javascript:void(0);">'._PAGEMASTER_DATAADDITEM.'</a>
                 </div>
                 <ul id="pmcustomdata" class="itemlist">
                     <li class="itemlistheader">
                       <div class="pn-clearfix">
                       <span class="itemlistcell width22">'._PAGEMASTER_DATANAME.'</span>
                       <span class="itemlistcell width22">'._PAGEMASTER_DATADISPLAY.'</span>
                       <span class="itemlistcell width22">'._PAGEMASTER_DATAAPITOUSE.'</span>
                       <span class="itemlistcell width22">'._PAGEMASTER_DATAAJAXCALLTO.'</span>
                       <span class="itemlistcell width10">'._OPTIONS.'</span>
                       </div>
                     </li>';

        foreach ($vars as $key => $var) {
            $html .= '<li id="listitem_pmcustomdata_'.$key.'" class="listitem_pmcustomdata">
                        <div class="pn-clearfix">
                        <span class="itemlistcell width22">
                          <input id="itemname_'.$key.'" name="itemname[]" value="'.($var[0] != '~'? $var[0] : '').'" />
                        </span>
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemdisplay_'.$key.'" name="itemdisplay[]" value="'.($var[1] != '~'? $var[1] : '').'" />
                        </span>
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemapi_'.$key.'" name="itemapi[]" value="'.($var[2] != '~'? $var[2] : '').'" />
                        </span>
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemajax_'.$key.'" name="itemajax[]" value="'.($var[3] != '~'? $var[3] : '').'" />
                        </span>
                        <span class="itemlistcell width10">
                          <button id="buttondelete_pmcustomdata_'.$key.'" class="buttondelete"><img height="16" width="16" title="'._DELETE.'" alt="'._DELETE.'" src="images/icons/extrasmall/14_layer_deletelayer.gif"/></button>
                        </span>
                        </div>
                      </li>';
        }

        $html .= '</ul>
                  <ul style="display:none">
                      <li id="pmcustomdata_emptyitem">
                        <div class="pn-clearfix">
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemname_" name="dummy[]" />
                        </span>
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemdisplay_" name="dummy[]" />
                        </span>
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemapi_" name="dummy[]" />
                        </span>
                        <span class="itemlistcell width22">
                          <input class="iteminput" id="itemajax_" name="dummy[]" />
                        </span>
                        <span class="itemlistcell width10">
                          <button id="buttondelete_pmcustomdata_X" class="buttondelete"><img height="16" width="16" title="'._DELETE.'" alt="'._DELETE.'" src="images/icons/extrasmall/14_layer_deletelayer.gif"/></button>
                        </span>
                        </div>
                      </li>
                  </ul>';

        $html .= '<script>
                  //<![CDATA[
                      var itemlist_pmcustomdata = null;
                      debug = null;
                      Event.observe(window, \'load\', function() {
                          itemlist_pmcustomdata = new Zikula.itemlist(\'pmcustomdata\', {headerpresent: true});
                      }, false);
                  //]]>
                  </script>
                  </div>';

        return $html;
    }

    /**
     * Method to extract the config values
     * @TODO: protect this method
     */
    function parseConfig($arrayConfig, $indexKey=null)
    {
        $result = array();
        $arrayConfig = explode('||', $arrayConfig);
        foreach ($arrayConfig as $row) {
            $tmp = explode('|', $row);
            if (!is_null($indexKey) && isset($tmp[$indexKey]) && !empty($tmp[$indexKey])) {
                $result[$tmp[$indexKey]] = $tmp;
            } else {
                $result[] = $tmp;
            }
        }
        return $result;
    }

    /**
     * Method to parse a special string call 
     * @TODO: protect this method
     */
    function parseCall($call, $data=null)
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $key  = "%$key%";
                $call = str_replace($key, $value, $call);
            }
        }

        // parse the call
        $call = explode(':', $call);
        // call[0] should be the module name
        if (isset($call[0]) && !empty($call[0])) { 
            $modname = $call[0];
            // default for params
            $params = array();
            // call[1] can be a function or function&param=value
            if (isset($call[1]) && !empty($call[1])) {
                $callparts = explode('&', $call[1]); 
                $func = $callparts[0];
                unset($callparts[0]);
                if (count($callparts) > 0) {
                    foreach ($callparts as $callpart) {
                        $part = explode('=', $callpart);
                        $params[trim($part[0])] = trim($part[1]);
                    }
                }
            } else {
                $func = 'main';
            } 
            // addon: call[2] can be the type parameter, default 'user'
            $type = (isset($call[2]) &&!empty($call[2])) ? $call[2] : 'user';

            return array($modname, $type, $func, $params);
        }
        return ''; 
    }
}