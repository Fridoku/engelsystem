<?php

use Engelsystem\Database\DB;
use Engelsystem\ShiftsFilter;

/**
 * @return string
 */
function admin_groupFilters_title()
{
    return __('GroupFilters');
}

/**
 * @return string
 */
function admin_groupFilters()
{
  $session = session();
  $request = request();


  if (!$request->has('action')) {

    $groupFilters = DB::select('
        SELECT `id` , `name`, `priority`,
              (SELECT GROUP_CONCAT(`AngelTypes`.`name` ORDER BY `AngelTypes`.`name`)
               FROM `AngelTypes`
               WHERE `AngelTypes`.`type_filter` = `GroupFilters`.`id` )
               AS `combinedTypes`
        FROM `GroupFilters`
        ORDER BY `priority` DESC, `name`
    ');

    $filter_table = [];
    foreach ($groupFilters as $filter) {
      $filter_table[] = [
          'name'       => $filter['name'],
          'types' => $filter['combinedTypes'],
          'priority' => $filter['priority'],
          'actions'    => table_buttons([
            button(
              page_link_to('admin_group_filters',
                  ['action' => 'edit', 'id' => $filter['id']]),
              __('edit'),
              'btn-xs'
            ),
            button(
              page_link_to('admin_group_filters',
                  ['action' => 'delete', 'id' => $filter['id']]),
              __('delete'),
              'btn-xs'
            ),
          ]),
      ];
    }

    return page_with_title(admin_groupFilters_title(), [
        button(
            page_link_to('admin_group_filters',
                ['action' => 'edit', 'id' => '0']),
            __('New Filter'),
            'add',
        ),
        table([
            'name'       => __('Filter'),
            'types' => __('Angeltypes'),
            'priority' => __('Priority'),
            'actions'    => '',
        ], $filter_table),
    ]);
  }
  else {
    if ($request->has('id') && preg_match('/^\d{1,11}$/', $request->input('id'))) {

      $filter = getFilter($request->input('id'));
      if ($filter == null){
        error(__('Filter ID not found.'), true);
        redirect(page_link_to('admin_group_filters'));
      }

      switch ($request->input('action')) {
        case 'edit':
          return Filter_edit_view($filter);
          break;
        case 'delete':
          if ($filter['id'] == 0) {
            error(__('Cant delete filter ID 0'));
            redirect(page_link_to('admin_group_filters'));
          }
          if ($request->hasPostData('delete')) {
            DB::delete('
              DELETE FROM `GroupFilters`
              WHERE `id` = ?',
              [$filter['id']]
            );
            success(sprintf(__('Filter %s deleted.'), $filter['name']));
            redirect(page_link_to('admin_group_filters'));
          }
          return Filter_delete_view($filter);
          break;
        case 'save':
          Filter_save($request->input('id'));
          break;
        }
      }
      else {
        return error(__('Incomplete call, missing Filter ID.'), true);
      }
  }
}

/**
 * @return void
 * @param int $id The id to save to or 0 for new filter
 */
function Filter_save($id)
{
  $filter['id'] = $id;
  $filter['name'] = strip_request_item('name');
  $filter['showFilter'] = (bool)strip_request_item('showfilter');
  $filter['priority'] = (int)strip_request_item('priority');
  $filter['fortypes'] = check_request_int_array('fortypes');

  $shiftsFilter = new ShiftsFilter();
  update_ShiftsFilter($shiftsFilter, false, load_days());
  $filter['serialized'] = $shiftsFilter->serializeFilter();


  if ($filter['id'] == 0) {
    DB::insert('
      INSERT INTO `GroupFilters`
      (`name`, `showFilter`, `priority`, `serialized`)
      VALUES
      (?, ?, ?, ?)
    ', [$filter['name'], $filter['showFilter'], $filter['priority'], $filter['serialized']]);
    $filter['id'] = DB::getPdo()->lastInsertId();
  }
  else {
    DB::update('
      UPDATE `GroupFilters` SET
      `name` = ?, `showFilter` = ?, `priority` = ?, `serialized` = ?
      WHERE
      `id` = ?
    ', [$filter['name'], $filter['showFilter'], $filter['priority'], $filter['serialized'], $filter['id']]);
  }

  //Disassociate AngelTypes
  DB::update('
    UPDATE `AngelTypes` SET
    `type_filter` = NULL
    WHERE `type_filter` = ?
    ', [$filter['id']]);

  //Associate AngelTypes
  foreach($filter['fortypes'] as $t){
    DB::update('
      UPDATE `AngelTypes` SET
      `type_filter` = ?
      WHERE
      `id` = (?)
      ', [$filter['id'], $t]);
  }
    success(sprintf(__('Filter %s saved.'), $filter['name']));
    redirect(page_link_to('admin_group_filters'));
    return;
}

/**
 * @param mixed $filter The filter to delete
 * @return void
 */
function Filter_delete_view($filter)
{
    return page_with_title(sprintf(__('Delete filter %s'), $filter['name']), [
        info(sprintf(__('Do you want to delete filter %s?'), $filter['name']), true),
        form([
            buttons([
                button(page_link_to('admin_group_filters'), glyph('remove') . __('cancel')),
                form_submit(
                    'delete',
                    glyph('ok') . __('delete'),
                    'btn-danger',
                    false
                ),
            ]),
        ]),
    ]);
}

/**
 * @param mixed $filter The filter to edit
 * @return void
 */
function Filter_edit_view($filter)
{
  $days = load_days();
  $rooms = load_rooms();
  $types = load_types();


  $shiftsFilter = new ShiftsFilter();

  if ($filter['id'] == 0){
    $title = __('Create New Filter');
  }
  else {
    $title = __('Edit Filter');
    $shiftsFilter->loadSerialized($filter['serialized']);
  }




  //Create the array for the table
  $types_html = [];
  $types_checked = [];
  foreach($types as $type){
    if($type['type_filter'] == $filter['id']) $types_checked[] = $type['id'];
  }

  //Render the HTML for a shift filter and modify if so we can add more form elements
  $shiftsFilter_html = render_shift_filter($shiftsFilter, '', $days, $rooms, $types, 'Filter:', 'Save');
  //I am very sorry, this is where it gets ugly
  $shiftsFilter_html = str_replace([
    '<form class="form-inline" action="">
        <input type="hidden" name="p" value=user_shifts>',
    '</form>',
  ], '', $shiftsFilter_html);
  $shiftsFilter_html ='<div class=form-inline>' . $shiftsFilter_html . '</div>';

  //Build the form
  return page_with_title($title, [
    form([
      form_text('name', __('Name'), $filter['name']),
      form_spinner('priority', __('Priority'), $filter['priority']),
      form_element(__('Angeltypes'), ''),
      make_select($types, $types_checked,'fortypes'),
      form_element(__('User can view and change filter'), ''),
      form_checkbox('showfilter', __('Filter visible'), $filter['showFilter']),
      $shiftsFilter_html,


    ], page_link_to('admin_group_filters', ['action' => 'save', 'id' => $filter['id']])),
  ]);
}

/**
 * Get a filter by its id
 * @param int $id id of the filter or 0 to create a new one
 * @return mixed $filter
 */
function getFilter($id)
{
  if ($id == 0) {
    $filter['id'] = 0;
    $filter['name'] = '';
    $filter['showFilter'] = true;
    $filter['priority'] = 0;
  }
  else {
    $filter = DB::selectOne('
      SELECT *
      FROM `GroupFilters`
      WHERE `id` = ?
      ORDER BY `Name`
    ', [$id]);
  }
  return $filter;
}
