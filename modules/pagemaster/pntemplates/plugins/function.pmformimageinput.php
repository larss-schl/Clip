<?php
/**
 * PageMaster
 *
 * @copyright (c) 2008, PageMaster Team
 * @link        http://code.zikula.org/pagemaster/
 * @license     GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @version     $ Id $
 * @package     Zikula_3rd_party_Modules
 * @subpackage  pagemaster
 */

require_once('system/pnForm/plugins/function.pnformuploadinput.php');

class pmformimageinput extends pnFormUploadInput
{
    var $columnDef = 'C(256)';
    var $title     = 'Image Upload';
    var $upl_arr;

    function getFilename()
    {
        return __FILE__; // FIXME: may be found in smarty's data???
    }

	function render(&$render)
	{
		$input_html = parent::render($render);
		return $input_html.' '.$this->upl_arr['orig_name'];
	}

	function load(&$render, &$params)
	{
		$this->loadValue($render, $render->get_template_vars());
	}

	function loadValue(&$render, &$values)
	{
		if (array_key_exists($this->dataField, $values))
		    $value = $values[$this->dataField];

		if ($value !== null)
		    $this->upl_arr = unserialize($value);
	}

    function postRead($data, $field)
    {
        if (!empty($data)) {
            $arrTypeData = @unserialize($data);

            if (!is_array($arrTypeData)) {
                return LogUtil::registerError('error in pmformimageinput: stored data is invalid');
            }

            $DirPM = pnModGetVar('pagemaster', 'uploadpath');
            if ($arrTypeData['tmb_name'] <> '') {
                $this->upl_arr =  array(
                         'orig_name'    => $arrTypeData['orig_name'],
                         'thumbnailUrl' => $DirPM.'/'.$arrTypeData['tmb_name'],
                         'url'          => $DirPM.'/'.$arrTypeData['file_name']
                );
            } else {
                $this->upl_arr = array(
                         'orig_name'    => '',
                         'thumbnailUrl' => '',
                         'url'          => ''
                );
            }
            return $this->upl_arr;

        } else {
            return NULL;
        }
    }

    function preSave($data, $field)
    {
        $id   = $data['id'];
        $tid  = $data['tid'];
        $data = $data[$field['name']];

        // ugly to get old image from DB
        if ($id != NULL)
            $old_image = DBUtil::selectFieldByID('pagemaster_pubdata'.$tid, $field['name'], $id, 'id');

        if ($data['name'] <> '') {
            $uploadpath = pnModGetVar('pagemaster', 'uploadpath');

            // delete the old file
            if ($id != NULL) {
                $old_image_arr = unserialize($old_image);
                unlink($uploadpath. '/' .$old_image_arr['tmb_name']);
                unlink($uploadpath. '/' .$old_image_arr['file_name']);
            }
            list ($x, $y) = explode(':', $field['typedata']);
            $wh = array();
            if ($x > 0 and $y > 0) {
                $wh = array (
                    'w' => $x,
                    'h' => $y
                );
            }

            $srcTempFilename = $data['tmp_name'];
            $ext             = strtolower(getExtension($data['name']));
            $randName        = getNewFileReference();
            $new_filename    = $randName . '.' . $ext;
            $new_filenameTmb = $randName . '-tmb.' . $ext;
            $dstFilename     = $uploadpath . '/' . $new_filename;
            $dstFilenameTmb  = $uploadpath . '/' . $new_filenameTmb;

            copy($srcTempFilename, $dstFilename);

            $dstName = pnModAPIFunc('Thumbnail', 'user', 'generateThumbnail',
            array_merge($wh, array('filename'    => $dstFilename,
                                                           'dstFilename' => $dstFilenameTmb)));
            $arrTypeData = array(
                'orig_name' => $data['name'],
                'tmb_name'  => $new_filenameTmb,
                'file_name' => $new_filename
            );

            return serialize($arrTypeData);

        } elseif ($id != NULL) {
            // if it's not a new pub
            // return the old image if no new is selected
            return $old_image;
        }

        return NULL;
    }

    function getSaveTypeDataFunc($field)
    {
        $saveTypeDataFunc = 'function saveTypeData()
                             {
                                 $(\'typedata\').value = $F(\'pmplugin_x_px\')+\':\'+$F(\'pmplugin_y_px\');
                                 closeTypeData();
                             }';
        return $saveTypeDataFunc;
    }

    function getTypeHtml($field, $render)
    {
        $html = '<div class="pn-formrow">
                 <label for="pmplugin_x_px">x:</label><input type="text" id="pmplugin_x_px" name="pmplugin_x_px" />
                 <br />
                 <label for="pmplugin_y_px">y:</label><input type="text" id="pmplugin_y_px" name="pmplugin_y_px" />
                 </div>';
        return $html;
    }
}

function smarty_function_pmformimageinput($params, &$render) {
    return $render->pnFormRegisterPlugin('pmformimageinput', $params);
}
