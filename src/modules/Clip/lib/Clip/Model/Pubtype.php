<?php
/**
 * Clip
 *
 * @copyright  (c) Clip Team
 * @link       http://code.zikula.org/clip/
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package    Clip
 * @subpackage Model
 */

/**
 * This is the model class that define the entity structure and behaviours.
 */
class Clip_Model_Pubtype extends Doctrine_Record
{
    /**
     * Set table definition.
     *
     * @return void
     */
    public function setTableDefinition()
    {
        $this->setTableName('clip_pubtypes');

        $this->hasColumn('tid as tid', 'integer', 4, array(
            'primary' => true,
            'autoincrement' => true
        ));

        $this->hasColumn('title as title', 'string', 255, array(
            'notnull' => true,
            'default' => ''
        ));

        $this->hasColumn('urltitle as urltitle', 'string', 255, array(
            'notnull' => true,
            'default' => ''
        ));

        $this->hasColumn('description as description', 'string', 255, array(
            'notnull' => true,
            'default' => ''
        ));

        $this->hasColumn('fixedfilter as fixedfilter', 'string', 255);

        $this->hasColumn('defaultfilter as defaultfilter', 'string', 255);

        $this->hasColumn('itemsperpage as itemsperpage', 'integer', 4, array(
            'notnull' => true,
            'default' => 15
        ));

        $this->hasColumn('cachelifetime as cachelifetime', 'integer', 8, array(
            'notnull' => true,
            'default' => 0
        ));

        $this->hasColumn('sortfield1 as sortfield1', 'string', 255);

        $this->hasColumn('sortdesc1 as sortdesc1', 'boolean');

        $this->hasColumn('sortfield2 as sortfield2', 'string', 255);

        $this->hasColumn('sortdesc2 as sortdesc2', 'boolean');

        $this->hasColumn('sortfield3 as sortfield3', 'string', 255);

        $this->hasColumn('sortdesc3 as sortdesc3', 'boolean');

        $this->hasColumn('enableeditown as enableeditown', 'boolean', null, array(
            'notnull' => true,
            'default' => 0
        ));

        $this->hasColumn('enablerevisions as enablerevisions', 'boolean', null, array(
            'notnull' => true,
            'default' => 0
        ));

        $this->hasColumn('folder as folder', 'string', 255, array(
            'notnull' => true,
            'default' => ''
        ));

        $this->hasColumn('workflow as workflow', 'string', 255, array(
            'notnull' => true,
            'default' => ''
        ));

        $this->hasColumn('grouptype as grouptype', 'integer', 4);

        $this->hasColumn('config as config', 'clob', 65532);
    }

    /**
     * Record setup.
     *
     * @return void
     */
    public function setUp()
    {
        $this->actAs('Zikula_Doctrine_Template_StandardFields');

        $this->hasOne('Clip_Model_Grouptype as group', array(
              'local' => 'grouptype',
              'foreign' => 'gid'
        ));
    }

    /**
     * Clip utility methods
     */
    public function mapTitleField()
    {
        if ($this->contains('titlefield')) {
            return $this;
        }

        $this->mapValue('titlefield', Clip_Util::getTitleField($this->tid));

        return $this;
    }

    public function getTableName()
    {
        return 'clip_pubdata'.$this->tid;
    }

    public function getSchema()
    {
        return FileUtil::getFilebase($this->workflow);
    }

    private function toKeyValueArray($key, $field = null)
    {
        if (!$field) {
            return $this->$key;
        }

        $result = array();
        foreach ($this->$key as $k => $v) {
            if (!isset($v[$field])) {
                throw new Exception('Invalid field requested to Pubtype->getRelations().');
            }
            $result[$k] = $v[$field];
        }

        return $result;
    }

    public function getFields()
    {
        return $this->tid ? Clip_Util::getPubFields($this->tid) : array();
    }

    public function getRelations($onlyown = true, $field = null)
    {
        // TODO listen (own/all)relations get attempt
        $key = ($onlyown ? 'own' : 'all').'relations';

        if ($this->hasMappedValue($key)) {
            return $this->toKeyValueArray($key, $field);
        }

        $relations = array();

        // load own
        $records = Clip_Util::getRelations($this->tid, true);
        foreach ($records as $relation) {
            $relations[$relation['alias1']] = array(
                'id'       => $relation['id'],
                'tid'      => $relation['tid2'],
                'type'     => $relation['type'],
                'alias'    => $relation['alias1'],
                'title'    => $relation['title1'],
                'descr'    => $relation['descr1'],
                'opposite' => $relation['alias2'],
                'single'   => $relation['type']%2 == 0 ? true : false,
                'own'      => true
            );
        }

        if (!$onlyown) {
            // load foreign
            $records = Clip_Util::getRelations($this->tid, false);

            foreach ($records as $relation) {
                if (!isset($relations[$relation['alias2']])) {
                    $relations[$relation['alias2']] = array(
                        'id'       => $relation['id'],
                        'tid'      => $relation['tid1'],
                        'type'     => $relation['type'],
                        'alias'    => $relation['alias2'],
                        'title'    => $relation['title2'],
                        'descr'    => $relation['descr2'],
                        'opposite' => $relation['alias1'],
                        'single'   => $relation['type'] <= 1 ? true : false,
                        'own'      => false
                    );
                }
            }
        }

        $this->mapValue($key, $relations);

        return $this->toKeyValueArray($key, $field);
    }

    public function defaultConfig($config)
    {
        $default = array(
            'view' => array(
                'load' => false,
                'onlyown' => true,
                'processrefs' => false,
                'checkperm' => false,
                'handleplugins' => false,
                'loadworkflow' => false
            ),
            'display' => array(
                'load' => true,
                'onlyown' => true,
                'processrefs' => true,
                'checkperm' => true,
                'handleplugins' => false,
                'loadworkflow' => false
            ),
            'edit' => array(
                'load' => true,
                'onlyown' => true
            )
        );

        foreach ($default as $k => $v) {
            $config[$k] = isset($config[$k]) ? array_merge($v, $config[$k]) : $v;
        }

        return $config;
    }

    /**
     * Hydrate hook.
     *
     * @return void
     */
    public function postHydrate($event)
    {
        $pubtype = $event->data;

        if (is_object($pubtype)) {
            if (isset($pubtype->config) && !empty($pubtype->config) && is_string($pubtype->config)) {
                $pubtype->config = unserialize($pubtype->config);
            } else {
                $pubtype->config = array();
            }
            $pubtype->config = $this->defaultConfig($pubtype->config);

        } elseif (is_array($pubtype)) {
            if (isset($pubtype['config']) && !empty($pubtype['config']) && is_string($pubtype['config'])) {
                $pubtype['config'] = unserialize($pubtype['config']);
            } else {
                $pubtype['config'] = array();
            }
            $pubtype['config'] = $this->defaultConfig($pubtype['config']);
        }
    }

    /**
     * Utility method to create the hook bundles.
     *
     * @return void
     */
    public function registerHookBundles(Clip_Version &$clipVersion, $tid=null, $name=null)
    {
        $tid  = $tid ? $tid : $this->tid;
        $name = $name ? $name : $this->title;

        // display/edit hooks
        $bundle = new Zikula_HookManager_SubscriberBundle('Clip', "subscriber.ui_hooks.clip.pubtype{$tid}", 'ui_hooks', $clipVersion->__f('%s Item Hooks', $name));
        $bundle->addEvent('display_view',    "clip.ui_hooks.pubtype{$tid}.display_view");
        $bundle->addEvent('form_edit',       "clip.ui_hooks.pubtype{$tid}.form_edit");
        $bundle->addEvent('form_delete',     "clip.ui_hooks.pubtype{$tid}.form_delete");
        $bundle->addEvent('validate_edit',   "clip.ui_hooks.pubtype{$tid}.validate_edit");
        $bundle->addEvent('validate_delete', "clip.ui_hooks.pubtype{$tid}.validate_delete");
        $bundle->addEvent('process_edit',    "clip.ui_hooks.pubtype{$tid}.process_edit");
        $bundle->addEvent('process_delete',  "clip.ui_hooks.pubtype{$tid}.process_delete");
        $clipVersion->registerHookSubscriberBundle($bundle);

        // filter hooks
        $bundle = new Zikula_HookManager_SubscriberBundle('Clip', "subscriber.filter_hooks.clip.pubtype{$tid}", 'filter_hooks', $clipVersion->__f('%s Filter', $name));
        $bundle->addEvent('filter', "clip.filter_hooks.pubtype{$tid}.filter");
        $clipVersion->registerHookSubscriberBundle($bundle);
    }

    /**
     * Create hook.
     *
     * @return void
     */
    public function preInsert($event)
    {
        // make sure it belongs to a group (the first one after root)
        // TODO make this select-able on the pubtype form
        $gid = Doctrine_Core::getTable('Clip_Model_Grouptype')
                   ->createQuery()
                   ->select('gid')
                   ->orderBy('gid')
                   ->where('gid > ?', 1)
                   ->fetchOne(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);

        $this->grouptype = (int)$gid;
    }

    /**
     * Create hook.
     *
     * @return void
     */
    public function postInsert($event)
    {
        $clipVersion = new Clip_Version_Hooks();

        // register hook bundles
        $pubtype = $event->getInvoker();
        $this->registerHookBundles($clipVersion, $pubtype['tid'], $pubtype['title']);

        HookUtil::registerSubscriberBundles($clipVersion->getHookSubscriberBundles());

        // create the pubtype table
        Clip_Generator::updateModel($pubtype->tid);
        Doctrine_Core::getTable('ClipModels_Pubdata'.$pubtype->tid)->createTable();
    }

    /**
     * Saving hook.
     *
     * @return void
     */
    public function preSave($event)
    {
        if (is_array($this->config)) {
            $this->config = serialize($this->config);
        }
    }

    /**
     * Delete hook.
     *
     * @return void
     */
    public function postDelete($event)
    {
        $pubtype = $event->getInvoker();

        // delete m2m relation tables
        $ownSides = array(true, false);
        foreach ($ownSides as $ownSide) {
            $rels = Clip_Util::getRelations($pubtype['tid'], $ownSide);
            foreach ($rels as $tid => $relations) {
                foreach ($relations as $relation) {
                    if ($relation['type'] == 3) {
                        $table = 'ClipModels_Relation'.$relation['id'];
                        Doctrine_Core::getTable($table)->dropTable();
                    }
                }
            }
        }

        // delete any relation
        $where = array("tid1 = '{$pubtype['tid']}' OR tid2 = '{$pubtype['tid']}'");
        Doctrine_Core::getTable('Clip_Model_Pubrelation')->deleteWhere($where);

        // delete the data table
        Doctrine_Core::getTable('ClipModels_Pubdata'.$this->tid)->dropTable();

        // delete workflows
        DBUtil::deleteWhere('workflows', "module = 'Clip' AND obj_table = 'clip_pubdata{$pubtype['tid']}'");

        $clipVersion = new Clip_Version_Hooks();

        // unregister hook bundles
        $this->registerHookBundles($clipVersion, $pubtype['tid'], $pubtype['title']);

        HookUtil::unregisterSubscriberBundles($clipVersion);
    }
}
