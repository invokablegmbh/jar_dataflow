CREATE TABLE tt_content (
	dataflow_source int(11) DEFAULT '0',
	dataflow_items TEXT,
	dataflow_recursive int(11) DEFAULT '0',
	dataflow_max_items int(11) DEFAULT '0',
	dataflow_items_per_page int(11) DEFAULT NULL,
	dataflow_sysdirs varchar(250) DEFAULT '0',
	dataflow_category varchar(250) DEFAULT '0',
	dataflow_andor int(11) DEFAULT '0',
	dataflow_orderby varchar(10) DEFAULT 'ASC',
	dataflow_orderby_field varchar(250) DEFAULT '0',
);