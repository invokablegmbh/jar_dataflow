.. include:: /Includes.rst.txt
.. highlight:: typoscript
.. index:: Content Element
.. _configuration-content-element:

Content Element
==================

| With the :ref:`configuration-tcaoverride` each registered content element gets a new tab called 'Dataflow'.
| This contains the main configuration (constraints) on how to fetch the data of the desired table.
| 
| **Fields**
* **Source**: Source of your records. Choose a folder or select manually.
* **Recursive**: Include nested folders.
* **Maximum number of elements**: Return a fixed amount of items.
* **Sort by Field**: Select a column to sort by.
* **Sort Order**: ascending / descending.
* **Categories**: Show only items in the selected categories.
* **Category and/or linking**: Choose and / or - operator for the categoryies query.
* **Recursive**: Include nested folders.

.. image:: /Images/ce1.png

Pagination
-------------

| To activate automatic pagination see :ref:`configuration-tcaoverride` first.
| With *['enablePagination' => true]* there is an additional field 'Items per Page' in the dataflow tab at the bottom.
| It represents the amount of items of each paginated page.
| 

.. image:: /Images/ce2.png

| 
| **Output (items_pagination)**
* **itemsPerPage**: Items per page.
* **index**: Current page (starting at 0).
* **fullItemCount**: Amount of all items cross all pages.
* **count**: Amount of items on this page.
* **prev** *(array)*: Link to previous page (or to self if first page).
* **next** *(array)*: Link to next page (or to self if last page).
* **pages** *(array)*: List of all pages with links but without items.

.. image:: /Images/pagination1.png
*Example output of the prepared data for the pagination (itemsPerPage = 2).*

