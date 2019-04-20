
CREATE TABLE tree_data (
  id int(11) unsigned auto_increment primary key,
  parent_id int(11) unsigned,
  level int(11) unsigned,
  sort int(11) unsigned
);

CREATE TABLE tree_data_ext (
  id int(11) unsigned auto_increment primary key,
  parent_id int(11) unsigned,
  level int(11) unsigned,
  sort int(11) unsigned,
  title varchar(255) NULL,
  deleted BOOLEAN DEFAULT FALSE,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP,
  deleted_at datetime DEFAULT NULL
);

CREATE TABLE tree_structure (
  id int(11) unsigned auto_increment primary key,
  parent int(11) unsigned,
  child int(11) unsigned
);
