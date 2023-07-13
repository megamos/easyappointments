<link rel="stylesheet" type="text/css" href="<?= asset_url('/assets/ext/jquery-fullcalendar/fullcalendar.min.css') ?>">

<script src="<?= asset_url('assets/ext/jquery-fullcalendar/fullcalendar.min.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-jeditable/jquery.jeditable.min.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui-timepicker-addon.min.js') ?>"></script>
<script src="<?= asset_url('assets/js/working_plan_exceptions_modal.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_default_view.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_table_view.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_google_sync.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_appointments_modal.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_unavailability_events_modal.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_api.js') ?>"></script>

<script src="<?= asset_url('assets/js/backend_settings.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_settings_system.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_settings_user.js') ?>"></script>

<script>
    var GlobalVariables = {
        csrfToken: <?= json_encode($this->security->get_csrf_hash()) ?>,
        availableProviders: <?= json_encode($available_providers) ?>,
        availableServices: <?= json_encode($available_services) ?>,
        baseUrl: <?= json_encode($base_url) ?>,
        dateFormat: <?= json_encode($date_format) ?>,
        timeFormat: <?= json_encode($time_format) ?>,
        firstWeekday: <?= json_encode($first_weekday) ?>,
        editAppointment: <?= json_encode($edit_appointment) ?>,
        customers: <?= json_encode($customers) ?>,
        secretaryProviders: <?= json_encode($secretary_providers) ?>,
        calendarView: <?= json_encode($calendar_view) ?>,
        timezones: <?= json_encode($timezones) ?>,
        settings: {
            system: <?= json_encode($system_settings) ?>,
            user: <?= json_encode($user_settings) ?>
        },
        user: {
            id: <?= $user_id ?>,
            email: <?= json_encode($user_email) ?>,
            first_name: <?= json_encode($user_first_name) ?>,
            last_name: <?= json_encode($user_last_name) ?>,
            phone_number: <?= json_encode($user_phone_number) ?>,
            timezone: <?= json_encode($timezone) ?>,
            role_slug: <?= json_encode($role_slug) ?>,
            privileges: <?= json_encode($privileges) ?>,
        }
    };
    
    var addRoom = function(e) {
        if (e !== undefined) {
            e.preventDefault();
        }

        var roomsContainer = $('#rooms-container'),
        currentEntry = $(this).parents('.room:first'),
        newEntry = $(currentEntry.clone()).appendTo(roomsContainer);

        newEntry.find('input').val('');

        roomsContainer.find('.room:not(:last) .btn-add-room')
            .removeClass('btn-add-room').addClass('btn-remove-room')
            .removeClass('btn-success').addClass('btn-danger')
            .html('<i class="fas fa-minus-square"></i>');

        return newEntry.find('#extra-room');
    };

    var addRelative = function(e) {
        if (e !== undefined) {
            e.preventDefault();
        }

        var relativeContainer = $('#relatives-container'),
        currentEntry = $(this).parents('.relative:first'),
        newEntry = $(currentEntry.clone()).appendTo(relativeContainer);

        newEntry.find('input').val('');

        relativeContainer.find('.relative:not(:last) .btn-add-relative')
            .removeClass('btn-add-relative').addClass('btn-remove-relative')
            .removeClass('btn-success').addClass('btn-danger')
            .html('<i class="fas fa-minus-square"></i>');
    };

    var addGuest = function(e) {
        if (e !== undefined) {
            e.preventDefault();
        }

        var guestsContainer = $('#guests-container'),
        currentEntry = $(this).parents('.guest:first'),
        newEntry = $(currentEntry.clone()).appendTo(guestsContainer);

        newEntry.find('input').val('');

        guestsContainer.find('.guest:not(:last) .btn-add-guest')
            .removeClass('btn-add-guest').addClass('btn-remove-guest')
            .removeClass('btn-success').addClass('btn-danger')
            .html('<i class="fas fa-minus-square"></i>');
    };

    $(function () {
        BackendCalendar.initialize(GlobalVariables.calendarView);

        $(document)
        .on('click', '.btn-add-relative', addRelative)
        .on('click', '.btn-remove-relative', function(e) {
            $(this).parents('.relative:first').remove();

            e.preventDefault();
            return false;
        })
        
        .on('click', '.btn-add-guest', addGuest)
        .on('click', '.btn-remove-guest', function(e) {
            $(this).parents('.guest:first').remove();

            e.preventDefault();
            return false;
        })

        .on('click', '.btn-add-room', addRoom)
        .on('click', '.btn-remove-room', function(e) {
            $(this).parents('.room:first').remove();

            e.preventDefault();
            return false;
        })
        ;
    });
</script>

<div class="container-fluid backend-page" id="calendar-page">
    <div class="row" id="calendar-toolbar">
        <?php if ($role_slug == DB_SLUG_ADMIN): ?>
            <div id="calendar-filter" class="col-12 col-sm-5">
                <div class="form-group calendar-filter-items">
                    <select id="select-filter-item" class="form-control col"
                            data-tippy-content="<?= lang('select_filter_item_hint') ?>">
                    </select>
                </div>
            </div>
        <?php endif ?>

        <div id="calendar-actions" class="col-12 col-sm-7">
            <?php if (($role_slug == DB_SLUG_ADMIN || $role_slug == DB_SLUG_PROVIDER)
                && config('google_sync_feature') == TRUE): ?>
                <button id="google-sync" class="btn btn-primary"
                        data-tippy-content="<?= lang('trigger_google_sync_hint') ?>">
                    <i class="fas fa-sync-alt"></i>
                    <span><?= lang('synchronize') ?></span>
                </button>

                <button id="enable-sync" class="btn btn-light" data-toggle="button"
                        data-tippy-content="<?= lang('enable_appointment_sync_hint') ?>">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    <span><?= lang('enable_sync') ?></span>
                </button>
            <?php endif ?>

            <?php if ($privileges[PRIV_APPOINTMENTS]['add'] == TRUE): ?>
                <div class="btn-group">
                    <button class="btn btn-light" id="insert-appointment">
                        <i class="fas fa-plus-square mr-2"></i>
                        <?= lang('appointment') ?>
                    </button>

                    <button class="btn btn-light dropdown-toggle" id="insert-dropdown" data-toggle="dropdown">
                        <span class="caret"></span>
                        <span class="sr-only">Toggle Dropdown</span>
                    </button>

                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" id="insert-unavailable">
                            <i class="fas fa-plus-square mr-2"></i>
                            <?= lang('unavailable') ?>
                        </a>
                        <a class="dropdown-item" href="#" id="insert-working-plan-exception"
                            <?= $this->session->userdata('role_slug') !== 'admin' ? 'hidden' : '' ?>>
                            <i class="fas fa-plus-square mr-2"></i>
                            <?= lang('working_plan_exception') ?>
                        </a>
                    </div>
                </div>
            <?php endif ?>

            <button id="reload-appointments" class="btn btn-light"
                    data-tippy-content="<?= lang('reload_appointments_hint') ?>">
                <i class="fas fa-sync-alt"></i>
            </button>

            <button id="appointments-bg-colors" class="btn btn-light"
                    data-tippy-content="<?= lang('toggle_appointments_bg_colors_hint') ?>">
                <i class="fas fa-solid fa-brush"></i>
            </button>

            <?php if ($calendar_view === 'default'): ?>
                <!-- TODO: insert new href value when we have timeline view available -->
                <a class="btn btn-light is-disabled" href="#"
                   data-tippy-content="Tabell utseende utvecklas fortfarande">
                    <i class="fas fa-table is-disabled"></i>
                </a>
            <?php endif ?>

            <?php if ($calendar_view === 'resourceTimeline'): ?>
                <a class="btn btn-light" href="<?= site_url('backend?view=default') ?>"
                   data-tippy-content="<?= lang('default') ?>">
                    <i class="fas fa-calendar-alt"></i>
                </a>
            <?php endif ?>
        </div>
    </div>

    <div id="calendar"><!-- Dynamically Generated Content --></div>
</div>


<!-- MANAGE APPOINTMENT MODAL -->

<div id="manage-appointment" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= lang('edit_appointment_title') ?></h3>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body dynamic-wrap">
                <div class="modal-message alert d-none"></div>

                <form>
                    <fieldset>
                        <legend><?= lang('appointment_details_title') ?></legend>

                        <input id="appointment-id" type="hidden">

                        <!-- CLG CHANGE: Providing the possibility to book several rooms (services) at once -->

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                 <div class="row form-group">

                                      
                                </div>

                                <div class="form-group">
                                    <label for="select-service" class="control-label">
                                        <?= lang('service') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select id="select-service" class="required form-control">
                                        <?php
                                        // Group services by category, only if there is at least one service
                                        // with a parent category.
                                        $has_category = FALSE;
                                        foreach ($available_services as $service)
                                        {
                                            if ($service['category_id'] != NULL)
                                            {
                                                $has_category = TRUE;
                                                break;
                                            }
                                        }

                                        if ($has_category)
                                        {
                                            $grouped_services = [];

                                            foreach ($available_services as $service)
                                            {
                                                if ($service['category_id'] != NULL)
                                                {
                                                    if ( ! isset($grouped_services[$service['category_name']]))
                                                    {
                                                        $grouped_services[$service['category_name']] = [];
                                                    }

                                                    $grouped_services[$service['category_name']][] = $service;
                                                }
                                            }

                                            // We need the uncategorized services at the end of the list so we will use
                                            // another iteration only for the uncategorized services.
                                            $grouped_services['uncategorized'] = [];
                                            foreach ($available_services as $service)
                                            {
                                                if ($service['category_id'] == NULL)
                                                {
                                                    $grouped_services['uncategorized'][] = $service;
                                                }
                                            }

                                            foreach ($grouped_services as $key => $group)
                                            {
                                                $group_label = ($key != 'uncategorized')
                                                    ? $group[0]['category_name'] : 'Uncategorized';

                                                if (count($group) > 0)
                                                {
                                                    echo '<optgroup label="' . $group_label . '">';
                                                    foreach ($group as $service)
                                                    {
                                                        echo '<option value="' . $service['id'] . '">'
                                                            . $service['name'] . '</option>';
                                                    }
                                                    echo '</optgroup>';
                                                }
                                            }
                                        }
                                        else
                                        {
                                            foreach ($available_services as $service)
                                            {
                                                echo '<option value="' . $service['id'] . '">'
                                                    . $service['name'] . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>

                                    <div id="rooms-container">
                                          <div class="room input-group">
                                              <select id="extra-room" name="rooms[]" class="form-control">
                                                  <option value="" selected>Boka ett till rum...</option>
                                        <?php
                                        // Group services by category, only if there is at least one service
                                        // with a parent category.
                                        $has_category = FALSE;
                                        foreach ($available_services as $service)
                                        {
                                            if ($service['category_id'] != NULL)
                                            {
                                                $has_category = TRUE;
                                                break;
                                            }
                                        }

                                        if ($has_category)
                                        {
                                            $grouped_services = [];

                                            foreach ($available_services as $service)
                                            {
                                                if ($service['category_id'] != NULL)
                                                {
                                                    if ( ! isset($grouped_services[$service['category_name']]))
                                                    {
                                                        $grouped_services[$service['category_name']] = [];
                                                    }

                                                    $grouped_services[$service['category_name']][] = $service;
                                                }
                                            }

                                            // We need the uncategorized services at the end of the list so we will use
                                            // another iteration only for the uncategorized services.
                                            $grouped_services['uncategorized'] = [];
                                            foreach ($available_services as $service)
                                            {
                                                if ($service['category_id'] == NULL)
                                                {
                                                    $grouped_services['uncategorized'][] = $service;
                                                }
                                            }

                                            foreach ($grouped_services as $key => $group)
                                            {
                                                $group_label = ($key != 'uncategorized')
                                                    ? $group[0]['category_name'] : 'Uncategorized';

                                                if (count($group) > 0)
                                                {
                                                    echo '<optgroup label="' . $group_label . '">';
                                                    foreach ($group as $service)
                                                    {
                                                        echo '<option value="' . $service['id'] . '">'
                                                            . $service['name'] . '</option>';
                                                    }
                                                    echo '</optgroup>';
                                                }
                                            }
                                        }
                                        else
                                        {
                                            foreach ($available_services as $service)
                                            {
                                                echo '<option value="' . $service['id'] . '">'
                                                    . $service['name'] . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                              <span class="input-group-btn">
                                                  <button class="btn btn-success btn-add-room" type="button">
                                                      <i class="fas fa-plus-square"></i>
                                                  </button>
                                              </span>
                                          </div>
                                      </div>
                                </div>

                                <div class="form-group" style="display:none;">
                                    <label for="select-provider" class="control-label">
                                        <?= lang('provider') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select id="select-provider" class="required form-control"></select>
                                </div>
                                <?php if ($role_slug == DB_SLUG_ADMIN || $role_slug == DB_SLUG_PROVIDER): ?>
                                <div class="form-group">
                                </div>
                                <div class="form-group">
                                    <label class="my-1 mr-2" for="confirm"><?= lang('confirm') ?></label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="confirm" id="confirmAppointment">
                                    </div>
                                </div>
                                <?php endif ?>
                            </div>

                            <div class="col-12 col-sm-6">
                                <div class="form-group">
                                    <label for="start-datetime"
                                           class="control-label"><?= lang('start_date_time') ?></label>
                                    <input id="start-datetime" class="required form-control">
                                </div>

                                <div class="form-group">
                                    <label for="end-datetime" class="control-label"><?= lang('end_date_time') ?></label>
                                    <input id="end-datetime" class="required form-control">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <br>

                    <fieldset>
                        <legend>
                            <?= lang('customer_details_title') ?>
                            
                            <!-- CLG CHANGE: Only provide these buttons for admins and bokare (providers) -->
                            <?php if ($role_slug == DB_SLUG_ADMIN || $role_slug == DB_SLUG_PROVIDER): ?>
                                <button id="select-customer" class="btn btn-outline-secondary btn-sm" type="button"
                                        data-tippy-content="<?= lang('pick_existing_customer_hint') ?>">
                                    <i class="fas fa-hand-pointer mr-2"></i>
                                    <span>
                                        <?= lang('select') ?>
                                    </span>
                                </button>
                                <a style="font-size: 14px; color: #6c757d; border: 1px solid grey; border-radius: 3px; padding: 5px 8px;" href="<?= site_url('backend/users#secretaries') ?>" data-tippy-content="<?= lang('manage_users_hint') ?>">
                                    <i class="fas fa-plus mr-2"></i>
                                    Släkting
                                </a>
                                <input id="filter-existing-customers"
                                    placeholder="<?= lang('type_to_filter_customers') ?>"
                                    style="display: none;" class="input-sm form-control">
                                <div id="existing-customers-list" style="display: none;"></div>
                            <?php endif ?>

                        </legend>

                        <input id="customer-id" type="hidden">

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-group">
                                    <label for="first-name" class="control-label">
                                        <?= lang('first_name') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input id="first-name" type="text"class="required form-control" disabled>
                                </div>

                                <div class="form-group">
                                    <label for="last-name" class="control-label">
                                        <?= lang('last_name') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input id="last-name" class="required form-control" disabled>
                                </div>
                            </div>    
                            <div class="col-12 col-sm-6">
                                <div class="form-group">
                                    <label for="email" class="control-label">
                                        <?= lang('email') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input id="email" class="required form-control" disabled>
                                </div>

                                <div class="form-group">
                                    <label for="phone-number" class="control-label">
                                        <?= lang('phone_number') ?>
                                        <?php if ($require_phone_number === '1'): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif ?>
                                    </label>
                                    <input id="phone-number"
                                           class="form-control <?= $require_phone_number === '1' ? 'required' : '' ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="row form-group">
                            <legend>
                                <label style="margin-left: 15px;" for="relatives"> <?= lang('customer') ?> </label>
                                <button id="select-additional-customer" class="btn btn-outline-secondary btn-sm" type="button"
                                        data-tippy-content="<?= lang('pick_existing_customer_hint') ?>">
                                    <i class="fas fa-hand-pointer mr-2"></i>
                                    <span>
                                        <?= lang('add') ?>
                                    </span>
                                </button>
                                <input id="filter-additional-customers"
                                    placeholder="<?= lang('type_to_filter_customers') ?>"
                                    style="display: none;" class="input-sm form-control">
                                <div id="additional-customers-list" class="input-sm form-control" style="display: none;"></div>
                            </legend>
                            <div id="relatives-container" class="col-12 col-sm-9">
                                <div class="relative input-group hide">
                                    <input class="form-control" name="relatives[]" type="text" placeholder="Namn..." />
                                    <span class="input-group-btn">
                                        <button class="btn btn-danger btn-remove-relative" type="button">
                                            <i class="fas fa-minus-square"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12 col-sm-3">
                                <label for="relatives"> <?= lang('guest') ?> </label>
                            </div>
                            <div id="guests-container" class="col-12 col-sm-9">
                                <div class="guest input-group">
                                    <input class="form-control" name="guests[]" type="text" placeholder="Namn" />
                                    <span class="input-group-btn">
                                        <button class="btn btn-success btn-add-guest" type="button">
                                            <i class="fas fa-plus-square"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-12 col-sm-12">
                                <label for="appointment-notes" class="control-label">
                                    <?= lang('notes') ?>
                                </label>
                                <textarea id="appointment-notes" rows="8" class="form-control"></textarea>
                            </div>
                        </div>

                    </fieldset>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="fas fa-ban"></i>
                    <?= lang('cancel') ?>
                </button>
                <button id="save-appointment" class="btn btn-primary">
                    <i class="fas fa-check-square mr-2"></i>
                    <?= lang('save') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MANAGE UNAVAILABLE MODAL -->

<div id="manage-unavailable" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= lang('new_unavailable_title') ?></h3>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-message alert d-none"></div>

                <form>
                    <fieldset>
                        <input id="unavailable-id" type="hidden">

                        <div class="form-group">
                            <label for="unavailable-provider" class="control-label">
                                <?= lang('provider') ?>
                            </label>
                            <select id="unavailable-provider" class="form-control"></select>
                        </div>

                        <div class="form-group">
                            <label for="unavailable-start" class="control-label">
                                <?= lang('start') ?>
                                <span class="text-danger">*</span>
                            </label>
                            <input id="unavailable-start" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="unavailable-end" class="control-label">
                                <?= lang('end') ?>
                                <span class="text-danger">*</span>
                            </label>
                            <input id="unavailable-end" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="control-label"><?= lang('timezone') ?></label>

                            <ul>
                                <li>
                                    <?= lang('provider') ?>:
                                    <span class="provider-timezone">
                                        -
                                    </span>
                                </li>
                                <li>
                                    <?= lang('current_user') ?>:
                                    <span>
                                        <?= $timezones[$timezone] ?>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="form-group">
                            <label for="unavailable-notes" class="control-label">
                                <?= lang('notes') ?>
                            </label>
                            <textarea id="unavailable-notes" rows="3" class="form-control"></textarea>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="fas fa-ban"></i>
                    <?= lang('cancel') ?>
                </button>
                <button id="save-unavailable" class="btn btn-primary">
                    <i class="fas fa-check-square mr-2"></i>
                    <?= lang('save') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SELECT GOOGLE CALENDAR MODAL -->

<div id="select-google-calendar" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= lang('select_google_calendar') ?></h3>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="google-calendar" class="control-label">
                        <?= lang('select_google_calendar_prompt') ?>
                    </label>
                    <select id="google-calendar" class="form-control"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="fas fa-ban mr-2"></i>
                    <?= lang('cancel') ?>
                </button>
                <button id="select-calendar" class="btn btn-primary">
                    <i class="fas fa-check-square mr-2"></i>
                    <?= lang('select') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- WORKING PLAN EXCEPTIONS MODAL -->

<?php require __DIR__ . '/working_plan_exceptions_modal.php' ?>

