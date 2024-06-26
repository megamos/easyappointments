/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.2.0
 * ---------------------------------------------------------------------------- */

/**
 * Backend Calendar Appointments Modal
 *
 * This module implements the appointments modal functionality.
 *
 * @module BackendCalendarAppointmentsModal
 */
window.BackendCalendarAppointmentsModal = window.BackendCalendarAppointmentsModal || {};

(function (exports) {

    'use strict';

    function updateTimezone() {
        var providerId = $('#select-provider').val();

        var provider = GlobalVariables.availableProviders.find(function (availableProvider) {
            return Number(availableProvider.id) === Number(providerId);
        });

        if (provider && provider.timezone) {
            $('.provider-timezone').text(GlobalVariables.timezones[provider.timezone]);
        }
    }

    function bindEventHandlers() {
        /**
         * Event: Manage Appointments Dialog Save Button "Click"
         *
         * Stores the appointment changes or inserts a new appointment depending the dialog mode.
         */
        $('#manage-appointment #save-appointment').on('click', function () {
            // Before doing anything the appointment data need to be validated.
            if (!validateAppointmentForm()) {
                return;
            }

            // Prepare appointment data for AJAX request.
            var $dialog = $('#manage-appointment');

            // ID must exist on the object in order for the model to update the record and not to perform
            // an insert operation.

            var startDateTime = $dialog.find('#start-datetime').datepicker('getDate').set({'hour': 12, 'minute': 0, 'second': 0}).toString('yyyy-MM-dd  HH:mm:ss');
            var endDateTime = $dialog.find('#end-datetime').datepicker('getDate').set({'hour': 11, 'minute': 59, 'second': 0}).toString('yyyy-MM-dd HH:mm:ss');

            var appointment = {
                id_services: $dialog.find('#select-service').val(),
                id_users_provider: $dialog.find('#select-provider').val(),
                start_datetime: startDateTime,
                end_datetime: endDateTime,
                location: $dialog.find('#appointment-location').val(),
                bg_color: $dialog.find('#bg-color-input').val(), //for color picker
                notes: $dialog.find('#appointment-notes').val(),
                is_unavailable: false
            };

            if ($dialog.find('#appointment-id').val() !== '') {
                // Set the id value, only if we are editing an appointment.
                appointment.id = $dialog.find('#appointment-id').val();
            }

            if ($dialog.find('#confirmAppointment').is(":checked")) {
                appointment.status = "confirmed";
            } else {
                appointment.status = "pending";
            }

            var customer = {
                first_name: $dialog.find('#first-name').val(),
                last_name: $dialog.find('#last-name').val(),
                email: $dialog.find('#email').val(),
                phone_number: $dialog.find('#phone-number').val(),
                address: $dialog.find('#address').val(),
                city: $dialog.find('#city').val(),
                zip_code: $dialog.find('#zip-code').val(),
                notes: $dialog.find('#customer-notes').val()
            };


            //CLG change:
            // TODO: Also test case when Husmor or Admin books for someone
            if ($dialog.find('#customer-id').val() !== '') {
                // Set the id value, only if we are editing an appointment.
                var id = $dialog.find('#customer-id').val()

                customer.id = $dialog.find('#customer-id').val();
                appointment.id_users_customer = customer.id;
            }
            
            var additionalRooms = document.getElementsByName('rooms[]');

            if (additionalRooms.length > 0) {
                var rooms = [];

                additionalRooms.forEach((room_id) => {
                    var id = room_id.value.trim();

                    if (id.length > 0) {
                        rooms.push(id);
                    }

                });

                appointment.additional_rooms = rooms;
            }
            
            var relativesInput = document.getElementsByName('relatives[]');

            if (relativesInput.length > 1) {
                var relatives = [];
                relativesInput.forEach(function(user) { 
                    if (user.dataset.userid !== undefined) {
                        relatives.push(user.dataset.userid)
                    }
                });

                appointment.relatives = relatives;
            }
            
            var guestsInput = document.getElementsByName('guests[]');
            var firstGuest = guestsInput[0].value.trim();

            if (firstGuest.length > 0) {
                var guests = [];
                guestsInput.forEach(name => {
                    if (name.value.trim() != "") {
                        guests.push(name.value.trim())
                    }
                });

                appointment.guests = guests;
            }

            // zfine success callback.
            var successCallback = function (response) {
                // Display success message to the user.
                Backend.displayNotification(EALang.appointment_saved);

                // Close the modal dialog and refresh the calendar appointments.
                $dialog.find('.alert').addClass('d-none');
                $dialog.modal('hide');
                $('#select-filter-item').trigger('change');
            };

            // Define error callback.
            var errorCallback = function () {
                // $dialog.find('.modal-message').text(EALang.service_communication_error);
                // $dialog.find('.modal-message').addClass('alert-danger').removeClass('d-none');
                // $dialog.find('.modal-body').scrollTop(0);
            };

            // Save appointment data.
            BackendCalendarApi.saveAppointment(appointment, customer, successCallback, errorCallback);
        });

        /**
         * Event: Insert Appointment Button "Click"
         *
         * When the user presses this button, the manage appointment dialog opens and lets the user to
         * create a new appointment.
         */
        $('#insert-appointment').on('click', function () {
            $('.popover').remove();

            BackendCalendarAppointmentsModal.resetAppointmentDialog();
            var $dialog = $('#manage-appointment');

            // Set the selected filter item and find the next appointment time as the default modal values.
            if ($('#select-filter-item option:selected').attr('type') === 'provider') {
                var providerId = $('#select-filter-item').val();

                var providers = GlobalVariables.availableProviders.filter(function (provider) {
                    return Number(provider.id) === Number(providerId);
                });

                if (providers.length) {
                    $dialog.find('#select-service').val(providers[0].services[0]).trigger('change');
                    $dialog.find('#select-provider').val(providerId);
                }
            } else if ($('#select-filter-item option:selected').attr('type') === 'service') {
                $dialog.find('#select-service option[value="' + $('#select-filter-item').val() + '"]')
                    .prop('selected', true);
            } else {
                $dialog.find('#select-service option:first')
                    .prop('selected', true)
                    .trigger('change');
            }

            var serviceId = $dialog.find('#select-service').val();

            var service = GlobalVariables.availableServices.find(function (availableService) {
                return Number(availableService.id) === Number(serviceId);
            });

            var duration = service ? service.duration : 60;

            var start = new Date();
            start.set({'hour': 12, 'minute': 0, 'second': 0});
            
            $dialog.find('#start-datetime').val(GeneralFunctions.formatDate(start, GlobalVariables.dateFormat, true));
            $dialog.find('#end-datetime').val(GeneralFunctions.formatDate(start, GlobalVariables.dateFormat, true));

            // CLG CHANGE: Inserting user data if it's a relative (customer, that is a clone of a secretary) 
            //             that wants to create a new booking (appointment)
            var relative = GlobalVariables.user;

            if (relative) {
                $dialog.find('#customer-id').val(relative.id - 1);
                $dialog.find('#first-name').val(relative.first_name);
                $dialog.find('#last-name').val(relative.last_name);
                $dialog.find('#email').val(relative.email);
                $dialog.find('#phone-number').val(relative.phone_number);
                $dialog.find('#customer-notes').val(relative.notes);
            }

            // Display modal form.
            $dialog.find('.modal-header h3').text(EALang.new_appointment_title);
            $dialog.modal('show');
        });

        /**
         * Event: Pick Existing Customer Button "Click"
         */
        $('#select-customer').on('click', function () {
            var $list = $('#existing-customers-list');

            if (!$list.is(':visible')) {
                $(this).find('span').text(EALang.hide);
                $list.empty();
                $list.slideDown('slow');
                $('#filter-existing-customers').fadeIn('slow');
                $('#filter-existing-customers').val('');
                document.getElementById("filter-existing-customers").focus();

                GlobalVariables.customers.forEach(function (customer) {
                    $('<div/>', {
                        'class': 'list-group-item',
                        'data-id': customer.id,
                        'text': customer.first_name + ' ' + customer.last_name
                    })
                        .appendTo($list);
                });
            } else {
                $list.slideUp('slow');
                $('#filter-existing-customers').fadeOut('slow');
                $(this).find('span').text(EALang.select);
            }
        });

        /**
         * Event: Select Existing Customer From List "Click"
         */
        $('#manage-appointment').on('click', '#existing-customers-list div', function () {
            var customerId = $(this).attr('data-id');

            var customer = GlobalVariables.customers.find(function (customer) {
                return Number(customer.id) === Number(customerId);
            });

            if (customer) {
                $('#customer-id').val(customer.id);
                $('#first-name').val(customer.first_name);
                $('#last-name').val(customer.last_name);
                $('#email').val(customer.email);
                $('#phone-number').val(customer.phone_number);
            }

            $('#select-customer').trigger('click'); // Hide the list.
        });

        /**
         * Event: Pick Existing Customer Button "Click"
         */
        $('#select-additional-customer').on('click', function () {
            var $list = $('#additional-customers-list');
            if (!$list.is(':visible')) {
                $(this).find('span').text(EALang.hide);
                $list.empty();
                $list.slideDown('slow');
                $('#filter-additional-customers').fadeIn('slow');
                $('#filter-additional-customers').val('');
                document.getElementById("filter-additional-customers").focus();
                
                GlobalVariables.customers.forEach(function (customer) {
                    $('<div/>', {  
                        'class': 'list-group-item', 
                        'data-id': customer.id,
                        'text': customer.first_name + ' ' + customer.last_name
                    })
                        .appendTo($list);
                });
            } else {
                $list.slideUp('slow');
                $('#filter-additional-customers').fadeOut('slow');
                $(this).find('span').text(EALang.select);
            }
        });

        /**
         * Event: Select Existing Customer From List "Click"
         */
        $('#manage-appointment').on('click', '#additional-customers-list div', function () {
            var customerId = $(this).attr('data-id');

            var customer = GlobalVariables.customers.find(function (customer) {
                return Number(customer.id) === Number(customerId);
            });

            if (customer) {
                var relativeContainer = $('#relatives-container'),
                currentEntry = $(relativeContainer).children('.relative:first'),
                newEntry = $(currentEntry.clone()).appendTo(relativeContainer);
                var newEntryInput = newEntry.find('input');

                newEntry.removeClass('hide');
                newEntryInput.val(customer.first_name);
                newEntryInput.attr("data-userid", customer.id);
                newEntryInput.prop('disabled', true);
            }

            $('#select-additional-customer').trigger('click'); // Hide the list.
        });

        /**
         * Event: Filter Existing Customers "Change"
         */
        $('#filter-additional-customers').on('keyup', function () {
            var key = $(this).val().toLowerCase();
            var $list = $('#additional-customers-list');
            
            $list.empty();

            GlobalVariables.customers.forEach(function (customer) {
                var fullName = customer.first_name.toLowerCase() + ' ' + customer.last_name.toLowerCase();

                if (fullName.includes(key)) {
                    $('<div/>', {  
                        'class': 'list-group-item', 
                        'data-id': customer.id,
                        'text': customer.first_name + ' ' + customer.last_name
                    })
                        .appendTo($list);
                }
            });
        });

        var filterExistingCustomersTimeout = null;

        /**
         * Event: Filter Existing Customers "Change"
         */
        $('#filter-existing-customers').on('keyup', function () {
            if (filterExistingCustomersTimeout) {
                clearTimeout(filterExistingCustomersTimeout);
            }
            var key = $(this).val().toLowerCase();

            filterExistingCustomersTimeout = setTimeout(function() {
                var $list = $('#existing-customers-list');

                var url = GlobalVariables.baseUrl + '/index.php/backend_api/ajax_filter_customers';

                var data = {
                    csrfToken: GlobalVariables.csrfToken,
                    key: key
                };

                $('#loading').css('visibility', 'hidden');

                // Try to get the updated customer list.
                $.post(url, data)
                    .done(function (response) {
                        $list.empty();

                        response.forEach(function (customer) {
                            $('<div/>', {
                                'data-id': customer.id,
                                'text': customer.first_name + ' ' + customer.last_name
                            })
                                .appendTo($list);

                            // Verify if this customer is on the old customer list.
                            var result = GlobalVariables.customers.filter(function (globalVariablesCustomer) {
                                return Number(globalVariablesCustomer.id) === Number(customer.id);
                            });

                            // Add it to the customer list.
                            if (!result.length) {
                                GlobalVariables.customers.push(customer);
                            }
                        })
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        // If there is any error on the request, search by the local client database.
                        $list.empty();

                        GlobalVariables.customers.forEach(function (customer, index) {
                            if (customer.first_name.toLowerCase().indexOf(key) !== -1
                                || customer.last_name.toLowerCase().indexOf(key) !== -1
                                || customer.email.toLowerCase().indexOf(key) !== -1
                                || customer.phone_number.toLowerCase().indexOf(key) !== -1
                                || customer.address.toLowerCase().indexOf(key) !== -1
                                || customer.city.toLowerCase().indexOf(key) !== -1
                                || customer.zip_code.toLowerCase().indexOf(key) !== -1
                                || customer.notes.toLowerCase().indexOf(key) !== -1) {
                                $('<div/>', {
                                    'class': 'list-group-item',
                                    'data-id': customer.id,
                                    'text': customer.first_name + ' ' + customer.last_name
                                })
                                    .appendTo($list);
                            }
                        });
                    })
                    .always(function() {
                        $('#loading').css('visibility', '');
                    });
            }, 1000);
        });

        /**
         * Event: Selected Service "Change"
         *
         * When the user clicks on a service, its available providers should become visible. Also we need to
         * update the start and end time of the appointment.
         */
        $('#select-service').on('change', function () {
            var serviceId = $('#select-service').val();

            $('#select-provider').empty();

            // Automatically update the service duration.
            var service = GlobalVariables.availableServices.find(function (availableService) {
                return Number(availableService.id) === Number(serviceId);
            });

            var duration = service ? service.duration : 60;

            var start = $('#start-datetime').datepicker('getDate');
            $('#end-datetime').datepicker('setDate', new Date(start));

            // Update the providers select box.

            GlobalVariables.availableProviders.forEach(function (provider) {
                provider.services.forEach(function (providerServiceId) {
                    if (GlobalVariables.user.role_slug === Backend.DB_SLUG_PROVIDER && Number(provider.id) !== GlobalVariables.user.id) {
                        return; // continue
                    }

                    if (GlobalVariables.user.role_slug === Backend.DB_SLUG_SECRETARY && GlobalVariables.secretaryProviders.indexOf(provider.id) === -1) {
                        return; // continue
                    }

                    // If the current provider is able to provide the selected service, add him to the listbox.
                    if (Number(providerServiceId) === Number(serviceId)) {
                        $('#select-provider')
                            .append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
                    }
                });
            });
        });

        /**
         * Event: Provider "Change"
         */
        $('#select-provider').on('change', function () {
            updateTimezone();
        });

        /**
         * Event: Enter New Customer Button "Click"
         */
        $('#new-customer').on('click', function () {
            $('#manage-appointment').find('#customer-id, #first-name, #last-name, #email, '
                + '#phone-number, #address, #city, #zip-code, #customer-notes').val('');
        });
    }

    /**
     * Reset Appointment Dialog
     *
     * This method resets the manage appointment dialog modal to its initial state. After that you can make
     * any modification might be necessary in order to bring the dialog to the desired state.
     */
    exports.resetAppointmentDialog = function () {
        var $dialog = $('#manage-appointment');

        // Empty form fields.
        
        $dialog.find('input, textarea').val('');
        $dialog.find('.modal-message').fadeOut();

        // prepare colors
        $dialog.find('#colorRadio1').val('#FCA5A5');
        $dialog.find('#colorRadio2').val('#93C5FD');
        $dialog.find('#colorRadio3').val('#C4B5FD');
        $dialog.find('#colorRadio4').val('#a0d468');

        // Prepare service and provider select boxes.
        $dialog.find('#select-service').val(
            $dialog.find('#select-service').eq(0).attr('value'));

        // Fill the providers listbox with providers that can serve the appointment's
        // service and then select the user's provider.
        $dialog.find('#select-provider').empty();
        GlobalVariables.availableProviders.forEach(function (provider, index) {
            var canProvideService = false;

            var serviceId = $dialog.find('#select-service').val();

            var canProvideService = provider.services.filter(function (providerServiceId) {
                return Number(providerServiceId) === Number(serviceId)
            }).length > 0;

            if (canProvideService) { // Add the provider to the listbox.
                $dialog.find('#select-provider')
                    .append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
            }
        });

        // Close existing customers-filter frame.
        $('#existing-customers-list').slideUp('slow');
        $('#filter-existing-customers').fadeOut('slow');
        $('#select-customer span').text(EALang.select);

        // Setup start and datepickers.
        // Get the selected service duration. It will be needed in order to calculate the appointment end datetime.
        var serviceId = $dialog.find('#select-service').val();

        var service = GlobalVariables.availableServices.forEach(function (service) {
            return Number(service.id) === Number(serviceId);
        });

        // Reset rooms/services
        var roomsContainer = $dialog.find('#rooms-container');
        while (roomsContainer[0].childNodes.length > 2) {
            roomsContainer[0].removeChild(roomsContainer[0].lastChild);
        }
        
        roomsContainer.find('.room:last .btn-remove-room')
            .removeClass('btn-remove-room').addClass('btn-add-room')
            .removeClass('btn-danger').addClass('btn-success')
            .html('<i class="fas fa-plus-square"></i>');

        $dialog.find('#extra-room')[0].value = '';

        // Reset relatives and guests
        var relativesContainer = $dialog.find('#relatives-container');
        while (relativesContainer[0].childNodes.length > 2) {
            relativesContainer[0].removeChild(relativesContainer[0].lastChild);
        }
        
        //
         var guestsContainer = $dialog.find('#guests-container');

        guestsContainer[0].childNodes.forEach(function(n) {
            if(n.nodeName == '#text'){
                guestsContainer[0].removeChild(n);
            }
        });

        while (guestsContainer[0].childNodes.length > 1) {
            guestsContainer[0].removeChild(guestsContainer[0].firstChild);
        }

        guestsContainer.find('.guest:last .btn-remove-guest')
            .removeClass('btn-remove-guest').addClass('btn-add-guest')
            .removeClass('btn-danger').addClass('btn-success')
            .html('<i class="fas fa-plus-square"></i>');
                
        var startDateTime = new Date();
        var endDateTime = startDateTime;
        var dateFormat;

        switch (GlobalVariables.dateFormat) {
            case 'DMY':
                dateFormat = 'dd/mm/yy';
                break;
            case 'MDY':
                dateFormat = 'mm/dd/yy';
                break;
            case 'YMD':
                dateFormat = 'yy/mm/dd';
                break;
            default:
                throw new Error('Invalid GlobalVariables.dateFormat value.');
        }

        var firstWeekDay = GlobalVariables.firstWeekday;
        var firstWeekDayNumber = GeneralFunctions.getWeekDayId(firstWeekDay);

        $dialog.find('#start-datetime').datepicker({
            dateFormat: dateFormat,
            timeFormat: GlobalVariables.timeFormat === 'regular' ? 'h:mm TT' : 'HH:mm',

            // Translation
            dayNames: [EALang.sunday, EALang.monday, EALang.tuesday, EALang.wednesday,
                EALang.thursday, EALang.friday, EALang.saturday],
            dayNamesShort: [EALang.sunday.substr(0, 3), EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3), EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3), EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3)],
            dayNamesMin: [EALang.sunday.substr(0, 2), EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2), EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2), EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2)],
            monthNames: [EALang.january, EALang.february, EALang.march, EALang.april,
                EALang.may, EALang.june, EALang.july, EALang.august, EALang.september,
                EALang.october, EALang.november, EALang.december],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,
            timeOnlyTitle: EALang.select_time,
            timeText: EALang.time,
            hourText: EALang.hour,
            minuteText: EALang.minutes,
            firstDay: firstWeekDayNumber,
            onClose: function () {
                var serviceId = $('#select-service').val();

                // Automatically update the #end-datetime datepicker based on service duration.
                var service = GlobalVariables.availableServices.find(function (availableService) {
                    return Number(availableService.id) === Number(serviceId);
                });

                var start = $('#start-datetime').datepicker('getDate');
                $('#end-datetime').datepicker('setDate', new Date(start));
            }
        });
        $dialog.find('#start-datetime').datepicker('setDate', startDateTime);

        $dialog.find('#end-datetime').datepicker({
            dateFormat: dateFormat,
            timeFormat: GlobalVariables.timeFormat === 'regular' ? 'h:mm TT' : 'HH:mm',

            // Translation
            dayNames: [EALang.sunday, EALang.monday, EALang.tuesday, EALang.wednesday,
                EALang.thursday, EALang.friday, EALang.saturday],
            dayNamesShort: [EALang.sunday.substr(0, 3), EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3), EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3), EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3)],
            dayNamesMin: [EALang.sunday.substr(0, 2), EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2), EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2), EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2)],
            monthNames: [EALang.january, EALang.february, EALang.march, EALang.april,
                EALang.may, EALang.june, EALang.july, EALang.august, EALang.september,
                EALang.october, EALang.november, EALang.december],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,
            timeOnlyTitle: EALang.select_time,
            timeText: EALang.time,
            hourText: EALang.hour,
            minuteText: EALang.minutes,
            firstDay: firstWeekDayNumber
        });
        $dialog.find('#end-datetime').datepicker('setDate', endDateTime);
    };

    /**
     * Validate the manage appointment dialog data. Validation checks need to
     * run every time the data are going to be saved.
     *
     * @return {Boolean} Returns the validation result.
     */
    function validateAppointmentForm() {
        var $dialog = $('#manage-appointment');

        // Reset previous validation css formatting.
        $dialog.find('.has-error').removeClass('has-error');
        $dialog.find('.modal-message').addClass('d-none');

        try {
            // Check required fields.
            var missingRequiredField = false;

            $dialog.find('.required').each(function (index, requiredField) {
                if ($(requiredField).val() === '' || $(requiredField).val() === null) {
                    $(requiredField).closest('.form-group').addClass('has-error');
                    missingRequiredField = true;
                }
            });

            if (missingRequiredField) {
                throw new Error(EALang.fields_are_required);
            }

            // Check email address.
            if (!GeneralFunctions.validateEmail($dialog.find('#email').val())) {
                $dialog.find('#email').closest('.form-group').addClass('has-error');
                throw new Error(EALang.invalid_email);
            }

            // Check appointment start and end time.
            var start = $('#start-datetime').datepicker('getDate');
            var end = $('#end-datetime').datepicker('getDate');
            if (start > end) {
                $dialog.find('#start-datetime, #end-datetime').closest('.form-group').addClass('has-error');
                throw new Error(EALang.start_date_before_end_error);
            }

            // Check no duplicate services/rooms
            var selectedServices = [];
            var selectedService = $dialog.find('#select-service');
            var additionalRooms = document.getElementsByName('rooms[]');

            selectedServices.push(selectedService.val());

            if (additionalRooms.length > 0) {
                additionalRooms.forEach((room_id) => {
                    var id = room_id.value.trim();

                    if (id.length > 0) {
                        selectedServices.push(id);
                    }

                });
            }

            var hasDuplicates = new Set(selectedServices).size !== selectedServices.length;
            if (hasDuplicates) {
                selectedService.closest('.form-group').addClass('has-error');
                throw new Error(EALang.duplicate_service_selected_error);
            }

            return true;
        } catch (error) {
            $dialog.find('.modal-message').addClass('alert-danger').text(error.message).removeClass('d-none');
            return false;
        }
    }

    exports.initialize = function () {
        bindEventHandlers();
    };

})(window.BackendCalendarAppointmentsModal);
