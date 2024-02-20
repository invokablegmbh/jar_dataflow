<?php
defined('TYPO3') or die('Access denied.');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'tt_content',
    [
        'dataflow_source' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:source',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:folder', 0],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:manual', 1]
                ],
                'size' => '1',
                'maxitems' => '1',
                'eval' => '',
            ],
            'onChange' => 'reload'
        ],
        'dataflow_items' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:items',
            'config' => [
                'type' => 'group',
                'size' => '2',
                'maxitems' => '100',
                'autoSizeMax' => '5',
                'allowed' => 'tt_content',
                'internal_type' => 'db',
            ],
            'displayCond' => 'FIELD:dataflow_source:=:1',
        ],
        'dataflow_recursive' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:recursive',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:layer_0', 0],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:layer_1', 1],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:layer_2', 2],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:layer_3', 3],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:layer_4', 4],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:layer_5', 5],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:infinity', 6]
                ],
                'size' => '1',
                'maxitems' => '1',
                'eval' => ''
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_max_items' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:maxItems',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'int'
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_sysdirs' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:sysdirs',
            'config' => [
                'type' => 'group',
                'size' => '2',
                'maxitems' => '100',
                'autoSizeMax' => '5',
                'internal_type' => 'db',
                'allowed' => 'pages',
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_category' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectTree',
                'foreign_table' => 'sys_category',
                'foreign_table_where' =>  'AND (sys_category.sys_language_uid = 0 OR sys_category.l10n_parent = 0) ORDER BY sys_category.sorting',
                'size' => 10,
                'treeConfig' => [
                    'parentField' => 'parent',
                    'appearance' => [
                        'expandAll' => '',
                        'showHeader' => '',
                        'maxLevels' => '99',
                    ],
                ],
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_andor' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:andor',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:orlink', 0],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:andlink', 1],
                ],
                'size' => '1',
                'maxitems' => '1',
                'eval' => '',
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_orderby' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:orderby',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:ascending', 'ASC', 'actions-sort-amount-up'],
                    ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:descending', 'DESC', 'actions-sort-amount-down'],
                ],
                'size' => '1',
                'maxitems' => '1',
                'eval' => '',
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_orderby_field' => [
            'exclude' => false,
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:orderby_field',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'size' => '1',
                'maxitems' => '1',
                'eval' => '',
            ],
            'displayCond' => 'FIELD:dataflow_source:<=:0',
        ],
        'dataflow_items_per_page' => [
            'label' => 'LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:items_per_page',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'trim,null,int',                
                'default' => null,
            ],
        ]
    ]
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
    'tt_content',
    'dataflow_categorylink',
    'dataflow_category, dataflow_andor'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
    'tt_content',
    'dataflow_orderitems',
    'dataflow_orderby_field, dataflow_orderby'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
    'tt_content',
    'dataflow_sysdirsmax',
    'dataflow_sysdirs, --linebreak--, dataflow_recursive, dataflow_max_items'
);
