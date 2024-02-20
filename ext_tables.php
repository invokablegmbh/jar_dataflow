<?php


defined('TYPO3') || die();

call_user_func(function () {
    if (TYPO3 === 'BE') {
        $GLOBALS['TBE_STYLES']['skins']['jar_dataflow'] = [
            'name' => 'jar_dataflow',
            'stylesheetDirectories' => [
                'css' => 'EXT:jar_dataflow/Resources/Public/Css/'
            ]
        ];

        // Hook into Content Preview Footer
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawFooter']['dataflow'] = \Jar\Dataflow\Hooks\PreviewRendererHook::class;

        // Hook into Rendering of "dataflow_item" Field, to activate custom Constraints and Language Handling
        // $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class]['modifyQuery']['dataFlow'] would be a cleaner
        // way to hook into the query of record lists but we have no way to get the source field (in our case "dataflow_items") which triggered the record list 
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['getTable']['dataFlow'] = \Jar\Dataflow\Hooks\RecordListGetTableHook::class;
    }
});
