<?php

namespace Engelsystem\Migrations;

use Carbon\Carbon;
use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;

class AddTypeFiltersFeature extends Migration
{
    /**
     * Run the migration
     */
    public function up()
    {
        $this->schema->create('TypeFilters', function (Blueprint $table) {
            $table->integer('id', $autoIncrement=true)->unique();
            $table->string('name');
            $table->boolean('showFilter');
            $table->integer('priority');
            $table->text('serialized');
            $table->timestamps();
        });

        $connection = $this->schema->getConnection();

        //Add a colum to the Groups table to set a filter for each group
        $connection->unprepared('
                ALTER TABLE `AngelTypes`
                    ADD `type_filter` INT(11) DEFAULT NULL,
                    ADD CONSTRAINT `angeltypes_ibfk_1` FOREIGN KEY (`type_filter`) REFERENCES `TypeFilters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
        ');


        // Add the new permission
        $connection->unprepared('
                INSERT
                INTO `Privileges` (`name`, `desc`)
                VALUES ("admin_type_filters", "Setting default shift filters for groups");
        ');

        // Add permissions to Bürokraten and above to set type filters
        $connection->unprepared('
                INSERT
                INTO `GroupPrivileges` (`group_id`, `privilege_id`)
                VALUES (-60, (SELECT id FROM `Privileges` WHERE name = "admin_type_filters") );
        ');

    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $connection = $this->schema->getConnection();

        // Remove permissions to Bürokraten and above to set type filters
        $connection->unprepared('
                DELETE
                FROM `GroupPrivileges`
                WHERE id = (
                  SELECT id
                  FROM `Privileges`
                  WHERE name = "admin_type_filters"
                )
        ');

        // Delete the permission
        $connection->unprepared('
                DELETE
                FROM `Privileges`
                WHERE name = "admin_type_filters"
        ');

        //Revert the Groups table
        $connection->unprepared('
                ALTER TABLE `AngelTypes`
                    DROP FOREIGN KEY `angeltypes_ibfk_1`,
                    DROP `type_filter`;
        ');

        //Delete Table with type filters
        $this->schema->dropIfExists('TypeFilters');

    }
}
