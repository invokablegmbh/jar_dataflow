.. include:: /Includes.rst.txt
.. highlight:: typoscript
.. index:: Include Bootrap Grid

.. _configuration-typoscript:

=====================
Typoscript
=====================

| The registered content element has to implement the DataflowProcessor.
| Example for the CType `html`
|

| `Jar\Dataflow\DataProcessing\DataflowProcessor`
| 
| **Parameters**
* **as** *(string)*: Index of the processed data.
* **debug** *(bool)*: Frontent debugging of all items.

.. code-block:: typoscript

   tt_content.html.DataProcessing {
      10 = Jar\Dataflow\DataProcessing\DataflowProcessor
      10 {
         as = items
         debug = 0
      }
   }