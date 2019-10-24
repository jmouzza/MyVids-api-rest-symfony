CREATE DATABASE IF NOT EXISTS api_rest_symfony;
use api_rest_symfony;

CREATE TABLE IF NOT EXISTS users(
id              int(20) auto_increment not null,
name            varchar(200) not null,
surname         varchar(200) not null,
email           varchar(255) not null,
password        varchar(255) not null,
role            varchar(20),
created_at      datetime,
updated_at      datetime,
remember_token  varchar(255),
CONSTRAINT pk_users PRIMARY KEY(id),
CONSTRAINT uq_email UNIQUE(email)
)DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB; 

CREATE TABLE IF NOT EXISTS videos(
id              int(255) auto_increment not null, 
user_id         int(20) not null,
title           varchar(255) not null,
description     text,
url             varchar(255) not null,
status          varchar(255),
created_at      datetime,
updated_at      datetime,
CONSTRAINT pk_videos PRIMARY KEY(id),
CONSTRAINT fk_videos_user FOREIGN KEY(user_id) REFERENCES users(id)
)DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;
