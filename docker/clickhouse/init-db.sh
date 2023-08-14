#!/bin/bash
set -e

clickhouse client --password password -n <<-EOSQL
	CREATE TABLE kma.url (
    id UInt64,
    length UInt64,
    url String,
    created_at UInt64
  )
  ENGINE = MySQL('db','db','url','root','root')
EOSQL