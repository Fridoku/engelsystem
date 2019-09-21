<?php

namespace Engelsystem\Migrations;

use Carbon\Carbon;
use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;

class CreateGroupFilterConfigTable extends Migration
{
    /**
     * Run the migration
     */
    public function up()
    {
        $this->schema->create('groupFilters', function (Blueprint $table) {
            $table->integer('id', $autoIncrement=true)->unique();
            $table->boolean('showFilter');
            $table->string('serialized');
            $table->timestamps();
        });

        $connection = $this->schema->getConnection();

        //Add a colum to the Groups table to set a filter for each group
        $connection->unprepared(' ALTER TABLE `Groups`
                                    ADD `defaultFilter` INT(11) DEFAULT NULL,
                                    ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`defaultFilter`) REFERENCES `globalFilters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
                                    ');

    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $connection = $this->schema->getConnection();

        //Revert the Groups table
        $connection->unprepared(' ALTER TABLE `Groups`
                                    DROP `groups_ibfk_1`,
                                    DROP `defaultFilter`;
                                    ');
        $this->schema->dropIfExists('globalFilters');

    }
}
