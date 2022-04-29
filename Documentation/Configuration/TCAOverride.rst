.. include:: /Includes.rst.txt
.. index:: Configuration
.. _configuration-tcaoverride:

=====================
TCA Override
=====================

Basic
----------

| Each Content Element type has to be registered for dataflow via `TCA Overrides <https://docs.typo3.org/p/mask/mask/main/en-us/Guides/OverrideTCA.html>`__ .
| This is done with the function addDataflowFieldsToContentElement from
| 

.. php:namespace::  Jar\Dataflow\Utilities

.. php:class:: DataflowUtility

------------------------------------

.. php:method:: addDataflowFieldsToContentElement($cType, $foreignTable, $configuration)

    :param string $cType: CType of the content element.
    :param string $foreignTable: Table name of the foreign table.
    :param bool $enablePagination: Activates pagination options in Backend and outputs paginated elements.
    :param array $configuration: Advanced configuration.
    :param array $configuration['foreignSortableColumns']: Whitelist of columns which are selectable for sorting.
    :param array $configuration['foreignConstraints']: :ref:`developers`

.. code-block:: php

   \Jar\Dataflow\Utilities\DataflowUtility::addDataflowFieldsToContentElement('html', 'tx_j77template_utility_jobs', [
       'enablePagination' => true,
   ]);

.. _developers:

For developers
----------

.. confval:: array $foreignConstraints

    :param bool $userFunc: userFunc 
    :param bool $parameters: Parameters for the userFunc.

.. code-block:: php

   \Jar\Dataflow\Utilities\DataflowUtility::addDataflowFieldsToContentElement('html', 'tx_j77template_utility_jobs', [
       'foreignConstraints' => [
           [
               'userFunc' => \EXT\CustomNamespace\Constraints\CustomConstraints::class . '->getListConstraints',
               'parameters' => [
                   'hello' => 'world'
               ]
           ]
       ]
   ]);

|
| The userFunc can be used to add custom contraints for the crawled items. 
| E.g. all items with the uid greater 1330 and less than 1432.
| 

.. code-block:: php

   /**
   * @param ExpressionBuilder $expressionBuilder
   * @param array $params 
   * @return array 
   */
   public function getListConstraints(ExpressionBuilder $expressionBuilder, array $params = []): array {
       $table = $params['table'];
       $result = [
           $expressionBuilder->gte($table . '.uid', 1331),
           $expressionBuilder->lte($table . '.uid', 1431)
       ];
       return $result;
   }
