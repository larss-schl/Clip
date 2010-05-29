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

/**
 * Generates HTML vor Category Browsing
 *
 * @author kundi
 * @param $args['tid'] tid
 * @param $args['field'] fieldname of the pubfield which contains category
 * @param $args['template'] optional filename of template
 * @param $args['count'] optional count available pubs in this category
 * @param $args['multiselect'] are more selection in one browser allowed (makes only sense for multilist fields)
 * @param $args['globalmultiselect'] are more then one selections in all available browsers allowed
 * @param $args['togglediv'] this div will be toggled, if at least one entry is selected (if you wanna hidde cats as pulldownmenus)
 * @param $args['cache'] enable smarty cache
 * @param $args['cache_count'] enable count cache (apc is required)
 * @param $args['assign'] optional

 * @return html of category tree
 */
function smarty_function_category_browser($params, &$smarty)
{
    $dom = ZLanguage::getModuleDomain('PageMaster');

    $tid               = $params['tid'];
    $field             = $params['field'];
    $template          = isset($params['template']) ? $params['template'] : 'pagemaster_category_browser.htm';
    $count             = isset($params['count']) ? $params['count'] : false;
    $togglediv         = isset($params['togglediv']) ? $params['togglediv'] : false;
    $assign            = isset($params['assign']) ? $params['assign'] : null;
    $multiselect       = isset($params['multiselect']) ? $params['multiselect'] : false;
    $globalmultiselect = isset($params['globalmultiselect']) ? $params['globalmultiselect'] : false;
    $cache             = isset($params['cache']) ? $params['cache'] : false;
    $cache_count       = isset($params['cache_count']) ? $params['cache_count'] : true;

    $filter     = FormUtil::getPassedValue('filter');
    $filter_arr = explode(',', $filter);
    $lang       = ZLanguage::getLanguageCode();

    if (!$tid) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'tid', $dom));
    }

    if (!$field) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'field', $dom));
    }

    $cacheid = $tid.'-'.$field;
    $render  = pnRender::getInstance('PageMaster', $cache, $cacheid);

    if ($cache) {
        if ($render->is_cached($template,$cacheid)) {
            return $render->fetch($template,$cacheid);
        }
    }

    Loader::loadClass('CategoryUtil');

    $pubfields = PMgetPubFields($tid);
    $id = $pubfields[$field]['typedata'];

    $cats = CategoryUtil::getSubCategories($id);

    if (!$cats) {
        return LogUtil::registerError(__f('Error! No such category found for the ID passed [%s].', $id, $dom));
    }

    if ($count) {
        // get it only once
        $pubtype = PMgetPubType($tid);

        if (function_exists('apc_fetch') && $cache_count) {
            $count_arr_old = $count_arr = apc_fetch('cat_browser_count_'.$tid);
        }
    }

    $one_selected = false;
    foreach ($cats as $k => $v)
    {
        $v['selected'] = 0;
        $path = $v['path'];

        $old_filter = '';
        $filter_act = $field.':sub:'.$v['id'];

        $depth = StringUtil::countInstances($path, '/');
        if (strlen($path) > 1) {
            $depth++;
        }
        $depth = $depth-7;

        // check if this cat is filtered
        foreach ($filter_arr as $fkey => $fv)
        {
            if ($fv == $filter_act) {
                $v['selected'] = 1;
                $one_selected = true;
            } else {
                if ($multiselect) {
                    $old_filter = $old_filter.','.$fv;
                } else {
                    // just add if from another browser
                    if ($globalmultiselect) {
                        list($fx, $subx, $idx) = explode(':', $fv);

                        $found = false;
                        foreach ($cats as $k2 => $v2) {
                            if ($v2['id'] == $idx) {
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $old_filter = $old_filter.','.$fv;
                        }
                    }
                }
            }
        }

        // delete the ,
        $old_filter = substr($old_filter, 1);

        if ($v['selected'] == 1) {
            $new_filter = $old_filter;
        } else {
            if (!empty($old_filter)) {
                $new_filter = $old_filter.','.$filter_act;
            } else {
                $new_filter = $filter_act;
            }
        }

        if ($new_filter == '') {
            $url = pnModURL('PageMaster', 'user', 'main',
                            array('tid'    => $tid));
        } else {
            $url = pnModURL('PageMaster', 'user', 'main',
                            array('tid'    => $tid,
                                  'filter' => $new_filter));
        }

        if ($count) {
            if (isset($count_arr[$filter_act])) {
                $v['count'] = $count_arr[$filter_act];
            } else {
                $pubarr = pnModAPIFunc('PageMaster', 'user', 'pubList',
                                       array('tid'                => $tid,
                                             'countmode'          => 'just',
                                             'filter'             => $filter_act,
                                             'checkPerm'          => false,
                                             'pubfields'          => $pubfields,
                                             'pubtype'            => $pubtype,
                                             'handlePluginFields' => false));

                $count_arr[$filter_act] = $v['count'] = $pubarr['pubcount'];
            }
        }

        $v['depth']     = $depth;
        $v['url']       = $url;
        $v['fullTitle'] = isset($v['display_name'][$lang]) ? $v['display_name'][$lang] : $v['name'];

        $cats[$k] = $v;
    }

    // TODO Remove this in 1.3 to use DBUtil cache
    if (function_exists('apc_store') && $count && $cache_count && $count_arr_old <> $count_arr) {
        apc_store('cat_browser_count_'.$tid, $count_arr, 3600);
    }

    $render->assign('cats', $cats);

    $html = $render->fetch($template, $cacheid);

    if ($togglediv <> false && $one_selected) {
        $html .= '<script type=\'text/javascript\'>';
        //$html .= 'Effect.toggle(\''.$setup['field'].'\', \'blind\', {duration:0.0});';
        $html .= 'document.getElementById(\''.$togglediv.'\').style.display = \'block\';';
        $html .= '</script>';
    }

    if ($assign) {
        $smarty->assign($params['assign'], $html);
    } else {
        return $html;
    }
}