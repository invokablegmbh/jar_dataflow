<?php

declare(strict_types=1);

namespace Jar\Dataflow\Event;

use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;
use Jar\Dataflow\Utilities\DataflowUtility;
use Jar\Utilities\Utilities\TcaUtility;
use TYPO3\CMS\Backend\RecordList\RecordListGetTableHookInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList;

class ModifyDatabaseQueryForRecordListingDataflowEvent {

    public function __invoke(ModifyDatabaseQueryForRecordListingEvent $event): void
    {
        $table = $event->getTable();
        $recordList = $event->getDatabaseRecordList();
        $queryBuilder = $event->getQueryBuilder();
        
        // get related Data through a hacky way, but we just want to fire when a list is requested from tt_content.dataflow_items group element
        $relatingInfos = $this->getRelatingInformationsFromParentObject($recordList);
        if (
            !empty($relatingInfos['relatingTable']) &&
            $relatingInfos['relatingTable'] === 'tt_content' &&
            !empty($relatingInfos['relatingField']) &&
            $relatingInfos['relatingField'] === 'dataflow_items' &&
            !empty($relatingInfos['overrideUrlParameters']['bparams'])
        ) {
            // We need to get the dataflow TCA Configuration for that content element what has called. Unfortunaly, the only way
            // to get this (UID -> CType -> TCA Definition of dataflow_items), is to disassemble the overrideUrlParameters
            $tcaDefinition = [];
            $returnUrlBase = $relatingInfos['overrideUrlParameters']['bparams'];
            $re = '/data\[tt_content\]\[(\d+)\]\[dataflow_items\]/m';
            preg_match_all($re, $returnUrlBase, $matches, PREG_SET_ORDER, 0);
            if (!empty($matches[0][1])) {
                $uid = (int) $matches[0][1];
                $row = BackendUtility::getRecord('tt_content', $uid);
                $tcaDefinition = TcaUtility::getFieldDefinition('tt_content', 'dataflow_items', TcaUtility::getTypeFromRow('tt_content', $row));
            }

            // Check and load Constraint Function from TCA definition
            
            if (!empty($tcaDefinition['config']['foreign_constraints'])) {

                // Remove the actual whereClause and create one with our own language settings
                $currentLanguageId = (int) $row['sys_language_uid'];
                if($currentLanguageId == -1) {
                    $currentLanguageId = 0;
                }
                
                $langContraint = '((`sys_language_uid` < 0) or (`sys_language_uid` = '. $currentLanguageId .'))';
                $queryBuilder->andWhere(...[$langContraint]);
                // above $additionalWhereClause works fine for list in non-default-languages (just the current language and "all" would be visible)
                // but in language "default" we has to hide translated elements in other languages because they get postloaded directly in the list,
                // so we has to deactivate translations for that table, when in default-language
                if($currentLanguageId === 0) {
                    $recordList->hideTranslations .= (!empty($recordList->hideTranslations) ? ',' : '') . $table;                    
                }                

                $contraints = DataflowUtility::generateConstraintsFromUserFuncList($table, $tcaDefinition['config']['foreign_constraints']);
                if (!empty($contraints)) {
                    $queryBuilder->andWhere(...$contraints);
                }
            }
        }
    
    }

    /**
     * @param mixed $obj 
     * @return void 
     */
    private function getRelatingInformationsFromParentObject($obj)
    {
        $neededKeys = ['relatingTable', 'relatingField', 'overrideUrlParameters'];
        $result = [];
        foreach ((array) $obj as $key => $value) {
            $key = preg_replace('/[\W]/', '', $key);
            if (in_array($key, $neededKeys)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    
}