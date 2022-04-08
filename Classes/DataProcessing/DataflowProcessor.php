<?php

declare(strict_types=1);

namespace Jar\Dataflow\DataProcessing;

use Jar\Dataflow\Services\DataflowService;
use Jar\Utilities\Utilities\TcaUtility;
use Jar\Utilities\Utilities\TypoScriptUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/*
 * This file is part of the JAR/Dataflow project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 * 
 * Example:
 * @todo paste Example
 */

/** @package Jar\Dataflow\DataProcessing */
class DataflowProcessor implements DataProcessorInterface
{
    /**
     * Process data to interpreting Dataflow Query Builder Informations as resulting Items
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        $populatedProcessorConfiguration = TypoScriptUtility::populateTypoScriptConfiguration($processorConfiguration, $cObj);
        
        $table = $populatedProcessorConfiguration['table'] ?? 'tt_content';
        $row = $populatedProcessorConfiguration['row'] ?? $processedData['data'] ?? $processedData;

        $settings = [];
        // reduce the row just to dataflow settings
        foreach ($row as $key => $value) {
            if (strpos($key, 'dataflow_') === 0) {
                $settings[substr($key, 9)] = $value;
            }
        }
        // populate with TCA Settings
        $tcaSettings = TcaUtility::getFieldConfig($table, 'dataflow_items', TcaUtility::getTypeFromRow($table, $row));
        $settings['table'] = $tcaSettings['foreign_table'];
        $settings['constraints'] = $tcaSettings['foreign_constraints'];

        // override with custom processorConfiguration
        ArrayUtility::mergeRecursiveWithOverrule($settings, $populatedProcessorConfiguration ?? []);

        $dataflowService = GeneralUtility::makeInstance(DataflowService::class);
        $dataflowService->setPropertiesByConfigurationArray($settings);
        
        $dataflowService->setContentElementUid($row['uid'] ?? 0);

        $result = $dataflowService->loadItems();

        // handle nested dataprocessors
        if (!empty($processorConfiguration['dataProcessing.'])) {
            $contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class);
            $recordContentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            // if complete Processing is active, forward the whole resullist. 
            // Otherwise process each item separatly
            if ((bool) $settings['completeProcessing']) {
                $result = $contentDataProcessor->process(
                    $cObj,
                    $processorConfiguration,
                    [
                        'rows' => $result,
                        'table' => $settings['table']
                    ]
                );
            } else {                
                foreach($result as $key => $item) {                    
                    $recordContentObjectRenderer->start($item, $settings['table']);
                    $result[$key] = $contentDataProcessor->process($recordContentObjectRenderer, $processorConfiguration, $item);
                }
            }
        }

        if (!empty($populatedProcessorConfiguration['as'])) {
            $processedData[$populatedProcessorConfiguration['as']] = $result;
        } else {
            ArrayUtility::mergeRecursiveWithOverrule($processedData, $result);
        }

        // also add pagination informations if pagination is active
        if(!empty($settings['items_per_page']) && (int) $settings['items_per_page'] > 0) {
            $processedData[$populatedProcessorConfiguration['as'] . '_pagination'] = $dataflowService->getPaginationInformations();
        }

        return $processedData;
    }
}
