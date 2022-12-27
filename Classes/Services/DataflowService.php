<?php

declare(strict_types=1);

namespace Jar\Dataflow\Services;

use Jar\Dataflow\Utilities\DataflowUtility;
use Jar\Utilities\Utilities\FormatUtility;
use Jar\Utilities\Utilities\IteratorUtility;
use Jar\Utilities\Utilities\PageUtility;
use Jar\Utilities\Utilities\TcaUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/*
 * This file is part of the JAR/Dataflow project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */


/** 
 * @package Jar\Dataflow\Services 
 * Service Class interpreting Dataflow Query Builder Informations to resulting Items
 **/

class DataflowService
{
	/**
	 * Source Mode:
	 * 	0: Dynamic from given syspages
	 * 	1: Manual Selection
	 * @var int
	 */
	private int $sourceMode = 0;

	/**
	 * List of UIDs
	 * @var array
	 */
	private array $selectedItems = [];

	/**
	 * List of PIDs where the items should load dynamic
	 * @var array
	 */
	private array $sysdirs = [];

	/**
	 * How depth should we go in the pagetree?
	 * @var int
	 */
	private int $recursive = 0;

	/**
	 * Order Direction
	 * @var string
	 */
	private string $orderBy = QueryInterface::ORDER_ASCENDING;

	/**
	 * Order Field
	 * @var string
	 */
	private string $orderByField = 'uid';

	/**
	 * Items per Page, a value > 0 activates Pagination of Items
	 * @var null|int
	 */
	private ?int $itemsPerPage = 0;

	/**
	 * The maximum amount of loaded items (0 = all)
	 * @var int
	 */
	private int $maxItems = 0;

	/**
	 * List of sys_category UIDs
	 * @var array
	 */
	private array $categories = [];

	/**
	 * Mode how category conditions should be applied (0 = OR, 1 = AND)
	 * @var int
	 */
	private int $categoryAndOr = 0;

	/**
	 * table from where the items should be loaded
	 * @var string
	 */
	private string $table;

	/**
	 * constraints that should be taken into account when loading items
	 * @var array
	 */
	private array $contraints = [];

	/**
	 * Flag for Debug Mode
	 * @var bool
	 */
	private bool $debug = false;

	/**
	 * Configuration for final reflection
	 * @see EXT:jar_utilities/Classes/DataProcessing/ReflectionProcessor.php
	 * @todo Link to the manual
	 * @var array
	 */
	private array $reflectionConfiguration = [];

	/**
	 * UID of the current Content Element, used for pagination links
	 * @var integer
	 */
	private int $contentElementUid = 0;

	/**
	 * current pagination pageIndex
	 * @var null|int
	 */
	private ?int $paginationPageIndex = null;

	/**
	 * Paginationinformations for building FE elements
	 * @var array
	 */
	private array $paginationInformations = [];



	/**
	 * Core of this class, return the items
	 * @return array 
	 */
	public function loadItems(): array
	{
		if (empty($this->table)) {
			return [];
		}

		$connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
		$queryBuilder = $connectionPool->getQueryBuilderForTable($this->table);

		$constraints = $this->contraints;
		
		$query = $queryBuilder
			->select('*')
			->from($this->table);

		// just load matching items for the current language
		// have to do it on our own @see https://forge.typo3.org/issues/88955
		$languageConfig = TcaUtility::getL10nConfig($this->table);
		if ($languageConfig !== null) {
			$languageField = $languageConfig['languageField'];
			$currentLanguageUid = $this->currentLanguageId();
			$constraints[] = $queryBuilder->expr()->orX(
				$queryBuilder->expr()->eq($languageField, $currentLanguageUid),
				$queryBuilder->expr()->eq($languageField, -1)
			);
		}

		if ($this->sourceMode === 1) {
			// manual selection, just load the selected UIDs			
			$constraints[] = $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($this->selectedItems, Connection::PARAM_INT_ARRAY));
		} else {
			// dynamic selection via sysdirs			
			if (empty($this->sysdirs)) {
				return [];
			}
			$pids = PageUtility::getPidsRecursive(implode(',', $this->sysdirs), $this->recursive < 6 ? $this->recursive : 99);			
			$constraints[] = $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY));

			// Category Loading
			if (!empty($this->categories)) {
				// load the matching Item UIDs with separate Query. Since sub-queries are not cleanly possible with the QueryBuilder 
				// and you still have to rework join results via PHP, this solution produces the cleanest code.
				$categoryQueryBuilder = $connectionPool->getQueryBuilderForTable('sys_category_record_mm');
				$categoryConstraints = [
					$categoryQueryBuilder->expr()->in(
						'uid_local',
						$categoryQueryBuilder->createNamedParameter($this->categories, Connection::PARAM_INT_ARRAY)
					),
					$categoryQueryBuilder->expr()->eq(
						'tablenames',
						$categoryQueryBuilder->createNamedParameter($this->table)
					),
					$categoryQueryBuilder->expr()->eq(
						'fieldname',
						$categoryQueryBuilder->createNamedParameter('categories') // @todo: make this configurable
					)
				];

				$itemUidsWithMatchingCategories = $categoryQueryBuilder
					->selectLiteral('`uid_foreign`', 'count(uid_foreign) as `count`')
					->from('sys_category_record_mm')
					->andWhere(...$categoryConstraints)
					->groupBy('uid_foreign')
					->execute()
					->fetchAll();

				// when the categories are AND-linked filter all elements where the amound doesn't match the amount of selected categories
				if ($this->categoryAndOr === 1) {
					$selectedCategoryCount = count($this->categories);
					$itemUidsWithMatchingCategories = IteratorUtility::filter($itemUidsWithMatchingCategories, function ($item) use ($selectedCategoryCount) {
						return $item['count'] === $selectedCategoryCount;
					});
				}

				$itemUidsWithMatchingCategories = IteratorUtility::pluck($itemUidsWithMatchingCategories, 'uid_foreign');

				$constraints[] = $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($itemUidsWithMatchingCategories, Connection::PARAM_INT_ARRAY));
			}

			// Max Items
			if ($this->maxItems > 0) {
				$query->setMaxResults($this->maxItems);
			}

			// order of items
			if (!empty($this->orderByField)) {
				$query->orderBy($this->orderByField, $this->orderBy);
			}
		}

		$query->andwhere(...$constraints);		

		$this->initPagination($query);

		$items = $query->execute()->fetchAll();

		if ($this->sourceMode === 1) {
			// by manual selection, sort the results to backend order
			$sortedItems = [];
			foreach ($items as $key => $item) {
				$sortUid = array_search($item['uid'], $this->selectedItems);
				$sortedItems[$sortUid] = $item;
			}
			ksort($sortedItems);
			$items = $sortedItems;
		}

		if ($this->debug) {
			DebuggerUtility::var_dump($query->getSQL(), 'Item Query', 1, true, false);
		}

		return $items;
	}



	/**
	 * Adds pagination related stuff to DB Query, also generates matching Pagination Information (f.e. Links to pagination)
	 * @param QueryBuilder $buildedItemQuery 
	 * @return void 
	 */
	protected function initPagination(QueryBuilder $buildedItemQuery): void
	{
		// We decided against using "SimplePagination", because we have to rebuild everything anyway to add the links to the individual pages.
		if ((bool) $this->itemsPerPage && (int) $this->itemsPerPage > 0) {
			$countQuery = clone $buildedItemQuery;
			$fullRowCount = $countQuery->select('uid')->execute()->rowCount();
			$pageCount = (int) ceil($fullRowCount / $this->itemsPerPage);

			$params = GeneralUtility::_GP('dataflow_pagination');

			if (
				$this->paginationPageIndex === null &&
				!empty($params['index']) &&
				$params['contentelement'] == $this->contentElementUid
			) {
				$this->paginationPageIndex = (int) ($params['index'] ?? 0);
			}

			$pageIndex = $this->paginationPageIndex ?? 0;

			$limit = $this->itemsPerPage;
			// empty results when pageIndex is higher than the amount of possible pages
			if ($pageIndex >= $pageCount) {
				$limit = 0;
			}

			// calculate lower limit for last page when global max limit is set here
			if ($pageIndex === $pageCount - 1 && $this->maxItems > 0) {
				$limit = $fullRowCount - ($this->itemsPerPage * $pageIndex);
			}

			$buildedItemQuery
				->setFirstResult($this->itemsPerPage * $pageIndex)
				->setMaxResults($limit);

			// generate pagination information
			$currentPageUid = $this->currentPageUid();

			$paginationInformations = [
				'itemsPerPage' => $this->itemsPerPage,
				'index' => $pageIndex,
				'fullItemCount' => $fullRowCount,
				'count' => $pageCount,
				'prev' => false,
				'next' => false,
				'pages' => [],
			];

			$linkTemplate = 't3://page?uid=%d&dataflow_pagination[contentelement]=%d&dataflow_pagination[index]=%d#c%d - %s';

			for ($i = 0; $i < $pageCount; $i++) {
				$paginationInformations['pages'][] = [
					'link' => FormatUtility::buildLinkArray(sprintf($linkTemplate, $currentPageUid, $this->contentElementUid, $i, $this->contentElementUid, ($i === $pageIndex ? '"active"' : '-'))),
					'number' => $i + 1,
				];
			}

			if ($pageIndex > 0) {
				$paginationInformations['prev'] = FormatUtility::buildLinkArray(sprintf($linkTemplate, $currentPageUid, $this->contentElementUid, $pageIndex - 1, $this->contentElementUid, '"prev"'));
			}

			if ($pageIndex < ($pageCount - 1)) {
				$paginationInformations['next'] = FormatUtility::buildLinkArray(sprintf($linkTemplate, $currentPageUid, $this->contentElementUid, $pageIndex + 1, $this->contentElementUid, '"next"'));
			}

			$this->paginationInformations = $paginationInformations;
		}
	}

	/** @return int  */
	private function currentPageUid(): int
	{
		return (int) $GLOBALS['TSFE']->id;
	}


	private function currentLanguageId(): int
	{
		$context = GeneralUtility::makeInstance(Context::class);
		return (int) $context->getPropertyFromAspect('language', 'id');
	}



	/**
	 * Initialize properties by given (mostly tt_content) row
	 * Example for $configuration:
	 * [
	 *     source => 1,
	 *     items => '1402,1400,1389',
	 *     recursive => 0,
	 *     max_items => 0,
	 *     items_per_page => 0,
	 *     sysdirs => '3,2,1',
	 *     category => '13,16,21',
	 *     andor => 0,
	 *     orderby => 'DESC',
	 *     orderby_field => 'sorting'
	 *	   table => 'tx_j77template_items',
	 *     constraints => [
	 *       [
	 *           userFunc => 'J77\J77Template\Constraints\ItemConstraints->getListConstraints',
	 *           parameters => [
	 *               hello => 'world'
	 *           ]
	 *       ]
	 *   ]		
	 * ]
	 * @param array $configuration
	 * @return DataflowService 
	 */
	public function setPropertiesByConfigurationArray(array $configuration): self
	{
		$this->resetAllProperties();

		if (!empty($configuration['source'])) {
			$this->setSourceMode((int) $configuration['source']);
		}

		if (!empty($configuration['items'])) {
			$this->setSelectedItems(GeneralUtility::trimExplode(',', $configuration['items'], true));
		}

		if (!empty($configuration['recursive'])) {
			$this->setRecursive((int) $configuration['recursive']);
		}

		if (!empty($configuration['max_items'])) {
			$this->setMaxItems((int) $configuration['max_items']);
		}

		if (!empty($configuration['items_per_page'])) {
			$this->setItemsPerPage((int) $configuration['items_per_page']);
		}	

		if (!empty($configuration['sysdirs'])) {
			$this->setSysdirs(GeneralUtility::trimExplode(',', $configuration['sysdirs'], true));
		}

		if (!empty($configuration['category'])) {
			$this->setCategories(GeneralUtility::trimExplode(',', $configuration['category'], true));
		}

		if (!empty($configuration['andor'])) {
			$this->setCategoryAndOr((int) $configuration['andor']);
		}

		if (!empty($configuration['orderby'])) {
			$this->setOrderBy($configuration['orderby']);
		}

		if (!empty($configuration['orderby_field'])) {
			$this->setOrderByField($configuration['orderby_field']);
		} 

		if (!empty($configuration['table'])) {
			$this->setTable($configuration['table']);
		}

		if (!empty($configuration['constraints'])) {
			$this->setContraints($configuration['constraints']);
		}		

		if (!empty($configuration['reflection'])) {
			$this->setReflectionConfiguration($configuration['reflection']);
		}

		if (!empty($configuration['debug'])) {
			$this->setDebug((bool) $configuration['debug']);
		}

		return $this;
	}

	/**
	 * Set all properties to default
	 *
	 * @return void
	 */
	private function resetAllProperties() {
		$this->setSourceMode(0);
		$this->setSelectedItems([]);
		$this->setRecursive(0);
		$this->setMaxItems(0);
		$this->setItemsPerPage(0);
		$this->setSysdirs([]);
		$this->setCategories([]);
		$this->setCategoryAndOr(0);
		$this->setOrderBy(QueryInterface::ORDER_ASCENDING);
		$this->setOrderByField('uid');
		$this->setTable('');
		$this->setContraints([]);
		$this->setReflectionConfiguration([]);
		$this->setDebug(false);
	}

	/**
	 * Get 	orderMode
	 *
	 * @return  int
	 */
	public function getSourceMode()
	{
		return $this->sourceMode;
	}

	/**
	 * Set 	orderMode
	 *
	 * @param  int  $sourceMode 
	 *
	 * @return  self
	 */
	public function setSourceMode(int $sourceMode)
	{
		$this->sourceMode = $sourceMode;

		return $this;
	}

	/**
	 * Get list of UIDs
	 *
	 * @return  array
	 */
	public function getSelectedItems()
	{
		return $this->selectedItems;
	}

	/**
	 * Set list of UIDs
	 *
	 * @param  array  $selectedItems  List of UIDs
	 *
	 * @return  self
	 */
	public function setSelectedItems(array $selectedItems)
	{
		$this->selectedItems = $selectedItems;

		return $this;
	}

	/**
	 * Get list of PIDs where the items should load dynamic
	 *
	 * @return  array
	 */
	public function getSysdirs()
	{
		return $this->sysdirs;
	}

	/**
	 * Set list of PIDs where the items should load dynamic
	 *
	 * @param  array  $sysdirs  List of PIDs where the items should load dynamic
	 *
	 * @return  self
	 */
	public function setSysdirs(array $sysdirs)
	{
		$this->sysdirs = $sysdirs;

		return $this;
	}

	/**
	 * Get order Direction
	 *
	 * @return  string
	 */
	public function getOrderBy()
	{
		return $this->orderBy;
	}

	/**
	 * Set order Direction
	 *
	 * @param  string  $orderBy  Order Direction
	 *
	 * @return  self
	 */
	public function setOrderBy(string $orderBy)
	{
		$this->orderBy = $orderBy;

		return $this;
	}

	/**
	 * Get order Field
	 *
	 * @return  string
	 */
	public function getOrderByField()
	{
		return $this->orderByField;
	}

	/**
	 * Set order Field
	 *
	 * @param  string  $orderByField  Order Field
	 *
	 * @return  self
	 */
	public function setOrderByField(string $orderByField)
	{
		$this->orderByField = $orderByField;

		return $this;
	}

	/**
	 * Get items per Page
	 *
	 * @return  null|int
	 */
	public function getItemsPerPage()
	{
		return $this->itemsPerPage;
	}

	/**
	 * Set items per Page
	 *
	 * @param  null|int  $itemsPerPage  Items per Page
	 *
	 * @return  self
	 */
	public function setItemsPerPage(?int $itemsPerPage)
	{
		$this->itemsPerPage = $itemsPerPage;

		return $this;
	}

	/**
	 * Get the maximum amount of loaded items
	 *
	 * @return  int
	 */
	public function getMaxItems()
	{
		return $this->maxItems;
	}

	/**
	 * Set the maximum amount of loaded items
	 *
	 * @param  int  $maxItems  The maximum amount of loaded items
	 *
	 * @return  self
	 */
	public function setMaxItems(int $maxItems)
	{
		$this->maxItems = $maxItems;

		return $this;
	}

	/**
	 * Get list of sys_category UIDs
	 *
	 * @return  array
	 */
	public function getCategories()
	{
		return $this->categories;
	}

	/**
	 * Set list of sys_category UIDs
	 *
	 * @param  array  $categories  List of sys_category UIDs
	 *
	 * @return  self
	 */
	public function setCategories(array $categories)
	{
		$this->categories = $categories;

		return $this;
	}

	/**
	 * Get mode how category conditions should be applied
	 *
	 * @return  int
	 */
	public function getCategoryAndOr()
	{
		return $this->categoryAndOr;
	}

	/**
	 * Set mode how category conditions should be applied
	 *
	 * @param  int  $categoryAndOr  Mode how category conditions should be applied
	 *
	 * @return  self
	 */
	public function setCategoryAndOr(int $categoryAndOr)
	{
		$this->categoryAndOr = $categoryAndOr;

		return $this;
	}

	/**
	 * Get how depth should we go in the pagetree?
	 *
	 * @return  int
	 */
	public function getRecursive()
	{
		return $this->recursive;
	}

	/**
	 * Set how depth should we go in the pagetree?
	 *
	 * @param  int  $recursive  How depth should we go in the pagetree?
	 *
	 * @return  self
	 */
	public function setRecursive(int $recursive)
	{
		$this->recursive = $recursive;

		return $this;
	}

	/**
	 * Get constraints that should be taken into account when loading items
	 *
	 * @return  array
	 */
	public function getContraints()
	{
		return $this->contraints;
	}

	/**
	 * Set constraints that should be taken into account when loading items
	 *
	 * @param  array  $contraints  constraints that should be taken into account when loading items
	 *
	 * @return  self
	 */
	public function setContraints(array $contraints)
	{
		if(!count($contraints)) {
			$this->contraints = $contraints;
		} else {
			$this->contraints = DataflowUtility::generateConstraintsFromUserFuncList($this->table, $contraints);
		}

		return $this;
	}

	/**
	 * Get table from where the items should be loaded
	 *
	 * @return  string
	 */
	public function getTable()
	{
		return $this->table;
	}

	/**
	 * Set table from where the items should be loaded
	 *
	 * @param  string  $table  table from where the items should be loaded
	 *
	 * @return  self
	 */
	public function setTable(string $table)
	{
		$this->table = $table;

		return $this;
	}

	/**
	 * Get configuration for final reflection
	 *
	 * @return  array
	 */
	public function getReflectionConfiguration()
	{
		return $this->reflectionConfiguration;
	}

	/**
	 * Set configuration for final reflection
	 *
	 * @param  array  $reflectionConfiguration  Configuration for final reflection
	 *
	 * @return  self
	 */
	public function setReflectionConfiguration(array $reflectionConfiguration)
	{
		$this->reflectionConfiguration = $reflectionConfiguration;

		return $this;
	}

	/**
	 * Get flag for Debug Mode
	 *
	 * @return  bool
	 */
	public function getDebug()
	{
		return $this->debug;
	}

	/**
	 * Set flag for Debug Mode
	 *
	 * @param  bool  $debug  Flag for Debug Mode
	 *
	 * @return  self
	 */
	public function setDebug(bool $debug)
	{
		$this->debug = $debug;

		return $this;
	}

	/**
	 * Get uID of the current Content Element
	 *
	 * @return  integer
	 */
	public function getContentElementUid()
	{
		return $this->contentElementUid;
	}

	/**
	 * Set uID of the current Content Element
	 *
	 * @param  integer  $contentElementUid  UID of the current Content Element
	 *
	 * @return  self
	 */
	public function setContentElementUid($contentElementUid)
	{
		$this->contentElementUid = $contentElementUid;

		return $this;
	}


	/**
	 * Get current pagination pageIndex
	 *
	 * @return  null|int
	 */
	public function getPageIndex()
	{
		return $this->paginationPageIndex;
	}

	/**
	 * Set current pagination pageIndex
	 *
	 * @param  null|int  $pageIndex  current pagination pageIndex
	 *
	 * @return  self
	 */
	public function setPageIndex($pageIndex)
	{
		if ($pageIndex !== null && (int) $pageIndex < 0) {
			$pageIndex = 0;
		}
		$this->paginationPageIndex = $pageIndex;

		return $this;
	}

	/**
	 * Get paginationinformations for building FE elements
	 *
	 * @return  array
	 */
	public function getPaginationInformations()
	{
		return $this->paginationInformations;
	}
}
