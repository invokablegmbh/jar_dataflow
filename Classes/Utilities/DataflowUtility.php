<?php

declare(strict_types=1);

namespace Jar\Dataflow\Utilities;

use FluidTYPO3\Vhs\ViewHelpers\DebugViewHelper;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/*
 * This file is part of the JAR/Dataflow project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */


/** @package Jar\Dataflow\Utilities */
class DataflowUtility
{
	/**
	 * Add Dataflow Fields to Backend
	 *
	 * @param string $cType CType of ContentElement
	 * @param string $foreignTable Table where the items should be loaded
	 * @param array $configuration
	 *  $configuration['enablePagination'] => Activates Pagination Options in Backend and outputs paginated elements
	 * 	$configuration['foreignSortableColumns'] => Whitelist of Columns which are selectable for sorting, just useable columns (f.e. 'inputs') are useable
	 * 	$configuration['foreignConstraints']
	 * 		Array of userfuncs which are generating constrains for item queries in Backend (manual selection of items) and Frontend (dynamic selection via sysdirs)
	 * 		Example:
	 * 		'foreignConstraints' => [
	 * 			[
	 *				'userFunc' => \J77\J77Template\Constraints\PanConstraints::class . '->getListConstraints',
	 *				'parameters' => [
	 *					'hello' => 'world'
	 *				]
	 *			]
	 *		] 
	 * @return void
	 */
	public static function addDataflowFieldsToContentElement(string $cType, string $foreignTable, array $configuration = []): void
	{
		$baseConfiguration = [
			'enablePagination' => false,
			'foreignSortableColumns' => '',
			'foreignConstraints' => [],
		];
		ArrayUtility::mergeRecursiveWithOverrule($baseConfiguration, $configuration);

		$configuration = $baseConfiguration;
		$configuration['foreignSortableColumns'] = GeneralUtility::trimExplode(',', $configuration['foreignSortableColumns'], true);

		// base add fields to that ctype
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
			'tt_content',
			'--div--;LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:dataflow,dataflow_source,dataflow_items,--palette--;;dataflow_sysdirsmax,--palette--;;dataflow_orderitems,--palette--;;dataflow_categorylink' . ($configuration['enablePagination'] ? ',dataflow_items_per_page' : ''),
			$cType,
			'after:linkToTop'
		);

		$sortableColumns = static::getSortableColumns($foreignTable, $configuration['foreignSortableColumns']);

		$resultTcaConfig = [
			// set items-field
			'dataflow_items' => [
				'config' => [
					'allowed' => $foreignTable,
					'foreign_table' => $foreignTable,
					'foreign_constraints' => $configuration['foreignConstraints'],
				]
			],
			// set order fields
			'dataflow_orderby_field' => [
				'config' => [
					'items' => $sortableColumns
				]
			]
		];

		if(!(is_array($GLOBALS['TCA']['tt_content']['types'][$cType]['columnsOverrides'] ?? false))) {
			$GLOBALS['TCA']['tt_content']['types'][$cType]['columnsOverrides'] = [];
		}

		ArrayUtility::mergeRecursiveWithOverrule($GLOBALS['TCA']['tt_content']['types'][$cType]['columnsOverrides'], $resultTcaConfig);

		// helper flag for post-handling like backend previews
		$GLOBALS['TCA']['tt_content']['types'][$cType]['dataflowIsActive'] = true;

		// Activate Categorization for foreign Tables		
		if($foreignTable !== 'pages' && $foreignTable !== 'tt_content') {
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::makeCategorizable('jar_dataflow', $foreignTable);
		}
	}


	/**
	 * @param string $cType 
	 * @param string $foreignTable 
	 * @param array $foreignSortableColumns 
	 * @return array 
	 */
	protected static function getSortableColumns(string $foreignTable, array $foreignSortableColumns = []): array
	{
		$sortableColumns = [];

		// set UID as default ordering
		$sortableColumns[] = ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:creation', 'uid'];

		// add Sortby-Field 
		if (!empty($sortingField = $GLOBALS['TCA'][$foreignTable]['ctrl']['sortby'])) {
			$sortableColumns[] = ['LLL:EXT:jar_dataflow/Resources/Private/Language/locallang.xlf:backendsorting', $sortingField];
		}


		// get specific sorting fields for that table
		$systemColumns = ['sys_language_uid', 'l10n_parent', 'l10n_diffsource', 't3ver_label', 'hidden', 'starttime', 'endtime', 'categories', 'seo_title', 'og_title', 'twitter_title',  'cache_tags', 'url', 'target', 'subtitle', 'nav_title'];
		$sortableConfigTypes = ['input'];
		$disallowedEvals = ['email', 'password'];
		$disallowedRenderTypes = ['inputLink'];

		foreach (($GLOBALS['TCA'][$foreignTable]['columns'] ?? []) as $column => $definition) {
			$containsDisallowedEvals = Count(array_intersect($disallowedEvals,  GeneralUtility::trimExplode(',', strtolower($definition['config']['eval'] ?? ''))));
			if (
				!$containsDisallowedEvals &&
				!($definition['exclude'] ?? false) &&
				!in_array($column, $systemColumns) &&
				(!empty($definition['config']['type']) && in_array($definition['config']['type'], $sortableConfigTypes)) &&
				!in_array($definition['config']['renderType'] ?? [], $disallowedRenderTypes)
			) {
				// when whitelist/foreignSortableColumns is set, just use these
				if (!empty($foreignSortableColumns) && !in_array($column, $foreignSortableColumns)) {
					continue;
				}
				$sortableColumns[] = [$definition['label'], $column];
			}
		}

		return $sortableColumns;
	}


	/**	
	 * @param string $table 
	 * @param array $funcList 
	 * @return array 
	 */
	public static function generateConstraintsFromUserFuncList(string $table, array $funcList): array
	{
		$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);		
		$expressionBuilder = $queryBuilder->expr();
		// resolve functions
		$constaints = [];

		foreach ($funcList as $funcListEntry) {			
			if (is_string($funcListEntry)) {
				// Fallback if the Condition directly written as string
				$constaints += $funcListEntry;
			} else if (!empty($funcListEntry['userFunc'])) {

				$params = $funcListEntry['parameters'];
				$params['table'] = $table;
				$params['queryBuilder'] = $queryBuilder;

				$funcName = trim($funcListEntry['userFunc']);
				$parts = explode('->', $funcName);
				if (count($parts) === 2) {
					// It's a class/method
					// Check if class/method exists:
					if (class_exists($parts[0])) {
						// Create object
						$classObj = GeneralUtility::makeInstance($parts[0]);
						$methodName = (string)$parts[1];
						$callable = [$classObj, $methodName];
						if (is_callable($callable)) {
							// Call method:
							$constaints += call_user_func($callable, $expressionBuilder, $params);
						} else {
							$errorMsg = 'No method name \'' . $parts[1] . '\' in class ' . $parts[0];
							throw new \InvalidArgumentException($errorMsg, 1294585865);
						}
					} else {
						$errorMsg = 'No class named ' . $parts[0];
						throw new \InvalidArgumentException($errorMsg, 1294585866);
					}
				} elseif (function_exists($funcName) && is_callable($funcName)) {
					// It's a function
					$constaints += call_user_func($funcName, $expressionBuilder, $params);
				} else {
					$errorMsg = 'No function named: ' . $funcName;
					throw new \InvalidArgumentException($errorMsg, 1294585867);
				}
			}
		}

		return $constaints;
	}
}
