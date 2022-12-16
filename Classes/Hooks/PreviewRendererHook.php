<?php

declare(strict_types=1);

namespace Jar\Dataflow\Hooks;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Jar\Utilities\Services\ReflectionService;
use Jar\Utilities\Utilities\TcaUtility;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawFooterHookInterface;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PreviewRendererHook implements PageLayoutViewDrawFooterHookInterface
{

    /**
     * @param \TYPO3\CMS\Backend\View\PageLayoutView $parentObject Calling parent object
     * @param array $info
     * @param array $row Record row of tt_content
     *
     * @return void
     */
    public function preProcess(
        PageLayoutView &$parentObject,
        &$info,
        array &$row
    ) {
        if ($GLOBALS['TCA']['tt_content']['types'][$row['CType']]['dataflowIsActive'] ?? false) {

            $foreignTable = $GLOBALS['TCA']['tt_content']['types'][$row['CType']]['columnsOverrides']['dataflow_items']['config']['allowed'] ?? '';
            if (empty($foreignTable)) {
                return;
            }


            $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);

            $reflectionService
                ->setTableColumnWhitelist(['tt_content' => ['dataflow_*']])
                ->setTableColumnBlacklist(['tt_content' => ['dataflow_items']])
                ->setTableColumnRemoveablePrefixes(['tt_content' => ['dataflow_']]);

            $data = $reflectionService->buildArrayByRow($row, 'tt_content', 2);

            $warningIcon = '<span class="icon icon-size-small icon-state-default">
                <span class="icon-markup">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g class="icon-color"><circle cx="8" cy="12" r="1"/><path d="M8.5 10h-1l-.445-4.45A.5.5 0 0 1 7.552 5h.896a.5.5 0 0 1 .497.55L8.5 10z"/><path d="M8 2.008a.98.98 0 0 1 .875.515l5.536 9.992a.983.983 0 0 1-.013.993.983.983 0 0 1-.862.492H2.464a.983.983 0 0 1-.862-.492.983.983 0 0 1-.013-.993l5.536-9.992A.98.98 0 0 1 8 2.008m0-1a1.98 1.98 0 0 0-1.75 1.03L.715 12.032C-.024 13.364.94 15 2.464 15h11.072c1.524 0 2.488-1.636 1.75-2.97L9.749 2.04A1.98 1.98 0 0 0 8 1.009z"/></g></svg>
                </span>
            </span>';

            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $itemIcon = $iconFactory->getIconForRecord($foreignTable, [], Icon::SIZE_SMALL)->render();

            $content = '
                <div class="t3js-icon icon icon-size-small">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -128 768 768">
                        <path  fill="#FFF" stroke="transparent" transform="rotate(180, 320, 256)" d="M616 320h-48v-48c0-22.056-17.944-40-40-40H344v-40h48c13.255 0 24-10.745 24-24V24c0-13.255-10.745-24-24-24H248c-13.255 0-24 10.745-24 24v144c0 13.255 10.745 24 24 24h48v40H112c-22.056 0-40 17.944-40 40v48H24c-13.255 0-24 10.745-24 24v144c0 13.255 10.745 24 24 24h144c13.255 0 24-10.745 24-24V344c0-13.255-10.745-24-24-24h-48v-40h176v40h-48c-13.255 0-24 10.745-24 24v144c0 13.255 10.745 24 24 24h144c13.255 0 24-10.745 24-24V344c0-13.255-10.745-24-24-24h-48v-40h176v40h-48c-13.255 0-24 10.745-24 24v144c0 13.255 10.745 24 24 24h144c13.255 0 24-10.745 24-24V344c0-13.255-10.745-24-24-24z"/>
                    </svg>
                </div> ';


            $statusClass = 'info';

            if ($data['source'] === 1) {
                // manual selection
                $countOfSelectedItems = Count(GeneralUtility::trimExplode(',', $row['dataflow_items'], true));
                if (!$countOfSelectedItems) {
                    $statusClass = 'warning';
                    $content .= LocalizationUtility::translate('manual_selection_none', 'jar_dataflow');
                    $content .= ' ' . $warningIcon;
                } else if ($countOfSelectedItems === 1) {
                    $content .= LocalizationUtility::translate('manual_selection_singular', 'jar_dataflow');
                    $content .= ' ' . $itemIcon;
                } else {
                    $content .= sprintf(LocalizationUtility::translate('manual_selection_plural', 'jar_dataflow'), $countOfSelectedItems);
                    $content .= ' ' . $itemIcon;
                }
            } else {
                // dynamic selection                
                if (empty($data['sysdirs'])) {
                    $statusClass = 'warning';
                    $content .= LocalizationUtility::translate('no_folder_selected', 'jar_dataflow');
                    $content .= ' ' . $warningIcon;
                } else {
                    // sysdirs
                    if (!$data['max_items']) {
                        $content .= LocalizationUtility::translate('sysdir_ext_all', 'jar_dataflow') . ': ';
                    } else {
                        $content .= sprintf(LocalizationUtility::translate('sysdir_ext_max', 'jar_dataflow'), $data['max_items']) . ': ';
                    }
                    foreach ($data['sysdirs'] as $sysdir) {
                        $content .= $iconFactory->getIconForRecord('pages', $sysdir, Icon::SIZE_SMALL)->render();
                        $content .= '<span class="dataflow-smallspacer"></span><strong>' . TcaUtility::getLabelFromRow($sysdir, 'pages') . '</strong> ';
                    }

                    // ordering
                    if (!empty($data['orderby_field'])) {
                        $orderByField = TcaUtility::getLabelOfSelectedItem($data['orderby_field'], 'dataflow_orderby_field', 'tt_content', $row['CType']);
                        $orderBy = TcaUtility::getLabelOfSelectedItem((string) $data['orderby'], 'dataflow_orderby', 'tt_content', $row['CType']);                        
                        $iconIdentfier = 'actions-sort-amount-' . ($data['orderby'] ? 'down' : 'up');
                        $content .= '<strong>/</strong> ' . $orderBy . ' '. LocalizationUtility::translate('sortby', 'jar_dataflow') . ' <strong>' . $orderByField . '</strong>';
                        $content .= '<span class="dataflow-smallspacer"></span>' . $iconFactory->getIcon($iconIdentfier, Icon::SIZE_SMALL)->render();                       
                    }
                    
                    // categories
                    if(!empty($data['category'])) {
                        $content .= '<strong>/</strong> ' . LocalizationUtility::translate('category_ext', 'jar_dataflow') . ': ';
                        $categoryList = [];
                        foreach ($data['category'] as $category) {
                            $categoryList[] = $iconFactory->getIconForRecord('sys_category', $category, Icon::SIZE_SMALL)->render() . '<strong>' . TcaUtility::getLabelFromRow($category, 'sys_category') . '</strong>';
                        }
                        $linkWord = LocalizationUtility::translate($data['andor'] ? 'and' : 'or', 'jar_dataflow');
                        $content .= implode('<em> ' . $linkWord . '</em> ', $categoryList);
                    }
                }
            }

            $content = '<div class="alert alert-' . $statusClass . ' jar-dataflow-preview-info" title="' . LocalizationUtility::translate('dataflow', 'jar_dataflow') . '">' . $content . '</div>';
            $info[] = $content;
        }
    }
}
