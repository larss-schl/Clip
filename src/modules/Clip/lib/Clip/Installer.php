<?php
/**
 * Clip
 *
 * @copyright   (c) Clip Team
 * @link        http://code.zikula.org/clip/
 * @license     GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package     Zikula_3rdParty_Modules
 * @subpackage  clip
 */

/**
 * Clip Installer.
 */
class Clip_Installer extends Zikula_Installer
{
    /**
     * Clip installation
     */
    public function install()
    {
        // create tables
        $tables = array(
            'Clip_Model_Pubfield',
            'Clip_Model_Pubtype',
            'Clip_Model_Pubrelation'
        );

        foreach ($tables as $table) {
            if (!Doctrine_Core::getTable($table)->createTable()) {
                foreach ($tables as $innertable) {
                    if ($innertable == $table) {
                        break;
                    }
                    Doctrine_Core::getTable($innertable)->dropTable();
                }
                return false;
            }
        }

        // build the default category tree
        $regpath = '/__SYSTEM__/Modules';

        $lang = ZLanguage::getLanguageCode();

        $rootcat = CategoryUtil::getCategoryByPath($regpath.'/clip');
        if (!$rootcat) {
            $rootcat = CategoryUtil::getCategoryByPath($regpath);

            $cat = new Categories_DBObject_Category();
            $cat->setDataField('parent_id', $rootcat['id']);
            $cat->setDataField('name', 'Clip');
            $cat->setDataField('display_name', array($lang => $this->__('Clip')));
            $cat->setDataField('display_desc', array($lang => $this->__('Clip root category')));
            if (!$cat->validate('admin')) {
                return LogUtil::registerError($this->__f('Error! Could not create the [%s] category.', 'Clip'));
            }
            $cat->insert();
            $cat->update();
        }

        $rootcat = CategoryUtil::getCategoryByPath($regpath.'/clip/lists');
        if (!$rootcat) {
            $rootcat = CategoryUtil::getCategoryByPath($regpath.'/clip');

            $cat = new Categories_DBObject_Category();
            $cat->setDataField('parent_id', $rootcat['id']);
            $cat->setDataField('name', 'lists');
            //! this is the 'lists' root category name
            $cat->setDataField('display_name', array($lang => $this->__('lists')));
            $cat->setDataField('display_desc', array($lang => $this->__('Clip lists for its publications')));
            if (!$cat->validate('admin')) {
                return LogUtil::registerError($this->__f('Error! Could not create the [%s] category.', 'lists'));
            }
            $cat->insert();
            $cat->update();
        }

        // create the PM category registry
        $rootcat = CategoryUtil::getCategoryByPath($regpath.'/clip/lists');
        if ($rootcat) {
            // create an entry in the categories registry to the Lists property
            $registry = new Categories_DBObject_Registry();
            $registry->setDataField('modname', 'Clip');
            $registry->setDataField('table', 'clip_pubtypes');
            $registry->setDataField('property', 'Lists');
            $registry->setDataField('category_id', $rootcat['id']);
            $registry->insert();
        } else {
            LogUtil::registerError($this->__f('Error! Could not create the [%s] Category Registry for Clip.', 'Lists'));
        }

        // modvars
        // upload dir creation if the temp dir is not outside the root (relative path)
        $tempdir = CacheUtil::getLocalDir();
        $tmpdir   = $tempdir.'/Clip';
        if (StringUtil::left($tempdir, 1) <> '/') {
            if (CacheUtil::createLocalDir('Clip')) {
                LogUtil::registerStatus($this->__f('Clip created the upload directory successfully at [%s]. Be sure that this directory is accessible via web and writable by the webserver.', $tmpdir));
            }
        } else {
            LogUtil::registerStatus($this->__f('Clip could not create the upload directory [%s]. Please create an upload directory, accessible via web and writable by the webserver.', $tmpdir));
        }

        $modvars = array(
            'uploadpath' => $tmpdir,
            'devmode'    => true
        );
        $this->setVars($modvars);

        return true;
    }

    /**
     * Clip upgrade
     */
    public function upgrade($oldversion)
    {
        switch ($oldversion)
        {
            case '0.4.0':
            case '0.4.1':
            case '0.4.2':
                $tables = DBUtil::getTables();
                // further upgrade handling
                // * map the field classnames to IDs
                // * rename the filename/formname columns
                // * fill the output/input sets if empty
                // * change C(512) to C(255) and X to C(65535)
                // * rename the columns to drop the pm_ prefix
                // * replace any pm_* in the pubtype sortfields
                // * verify create any non-existing pubtype table
                // * rename the clip:% permissions to clip:
                // * replace any occurence of pm* filters 

                // fills the empty publish dates
                $pubtypes = array_keys(Clip_Util::getPubType(-1)->toArray());
                if (!empty($pubtypes)) {
                    // update each pubdata table
                    // and update the new field value with the good old pm_cr_uid
                    $existingtables = DBUtil::metaTables();
                    foreach ($pubtypes as $tid) {
                        if (in_array(DBUtil::getLimitedTablename('clip_pubdata'.$tid), $existingtables)) {
                            $sql = "UPDATE {$tables['clip_pubdata'.$tid]} SET pm_publishdate = pm_cr_date WHERE pm_publishdate IS NULL";

                            if (!DBUtil::executeSQL($sql)) {
                                LogUtil::registerError($this->__('Error! Update attempt failed.'));
                                return '0.4.2';
                            }
                        }
                    }
                }
        }

        return true;
    }

    /**
     * Clip deinstallation
     */
    public function uninstall()
    {
        // drop pubtype tables
        $pubtypes = array_keys(Clip_Util::getPubType(-1)->toArray());

        foreach ($pubtypes as $tid) {
            $table = "Clip_Model_Pubdata$tid";
            if (!Doctrine_Core::getTable($table)->dropTable()) {
                return false;
            }
        }

        // FIXME Hooks, Workflows registries deleted?

        // drop base tables
        $tables = array(
            'Clip_Model_Pubfield',
            'Clip_Model_Pubtype',
            'Clip_Model_Pubrelation'
        );

        foreach ($tables as $table) {
            if (!Doctrine_Core::getTable($table)->dropTable()) {
                return false;
            }
        }

        // delete the category registry and modvars
        CategoryUtil::deleteCategoriesByPath('/__SYSTEM__/Modules/clip', 'path');
        $this->delVars();

        return true;
    }
}