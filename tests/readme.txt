Running tests with Docker

- install docker and docker-compose
- in terminal change direcroty to SortableTree
- build containers: docker-compose run -d --build
- enter the container: docker exec -it sortable-tree-php-fpm bash
- run MySql tests:
    php vendor/bin/codecept run unit SortableTreeMySqlTest
    php vendor/bin/codecept run unit SortableTreeExtendedMySqlTest
- run Postgres test:
    php vendor/bin/codecept run unit SortableTreePostgersTest
    php vendor/bin/codecept run unit SortableTreeExtendedPostgresTest
