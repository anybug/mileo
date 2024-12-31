import $ from 'jquery';
global.$ = global.jQuery = $;

$(document).ready(function () {

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});



$(document).ready(function () {
    $("#Report_Year_month").css("display", "none").val(1);
    $("#Report_Year_day").css("display", "none").val(1);
    $(".report_start_date ").parent().parent().parent().css("display", "none");
    $(".report_end_date ").parent().parent().parent().css("display", "none");

    initAutocomplete();
    dateTypeRange();

    $(document).on('click', '.field-collection-add-button', function () {
        initAutocomplete();
        dateTypeRange();
    });

    $(document).on('click', '.field-collection-delete-button', function () {
        totalForReport();
    });

    $(document).on('change', '.report_start_date', function () {
        dateTypeRange();
    });

    $(document).on('change', '.report_end_date', function () {
        dateTypeRange();
    });

    // calcule distance (module reportline) ******************************

    $(document).on('change focusout', '.lines_start', function () {
        let distance = $("form").find('.report_km');
        let dpt = $("form").find('.lines_start');
        let arv = $("form").find('.lines_end');
        delay(function () {
            calculDistance(null, distance, dpt, arv)
        }, 800);
    });

    $(document).on('change focusout', '.lines_end', function () {
        let distance = $("form").find('.report_km');
        let dpt = $("form").find('.lines_start');
        let arv = $("form").find('.lines_end');
        delay(function () {
            calculDistance(null, distance, dpt, arv)
        }, 800);
    });

    // calcule distance dans les lines (module report) ******************************

    $(document).on('change focusout', '.report_lines_start', function () {
        let line = $(this).parent().parent().parent().parent()
        let distance = line.find('.report_lines_km');
        let dpt = line.find('.report_lines_start');
        let arv = line.find('.report_lines_end');
        delay(function () {
            calculDistance(line, distance, dpt, arv)
        }, 800);
    });

    $(document).on('change focusout', '.report_lines_end', function () {
        let line = $(this).parent().parent().parent().parent()
        let distance = line.find('.report_lines_km');
        let dpt = line.find('.report_lines_start');
        let arv = line.find('.report_lines_end');
        delay(function () {
            calculDistance(line, distance, dpt, arv)
        }, 800);
    });

    // recalcule distance total si aller/retour change (module report et reportline)******************************

    $(".report_is_return").on('change', function () {
        calculTotalLineKm(null);
    });

    $(document).on('change', '.report_lines_is_return', function () {
        let line = $(this).parent().parent().parent().parent().parent()
        calculTotalLineKm(line);
    });

    // recalcule montant si scale change (module report et reportline)******************************

    $(document).on('change focusout', ".report_scale", function () {
        requestForGeneratingAmount();
    });

    $(document).on('change', ".report_lines_scale", function () {
        let line = $(this).parent().parent().parent().parent()
        requestForGeneratingAmount(line);
    });

    // changement des scale en fonction du vehicule (module report et reportline)******************************

    $(".report_vehicule").on('change', function () {

        requestForGeneratingAmount();
    });

    $(document).on('change', ".report_lines_vehicule", function () {

        let line = $(this).parent().parent().parent().parent();
        requestForGeneratingAmount(line);
    });

    // changement de power disponible en fonction du type (module vehicule) ******************************

    $(document).on('change', '.vehicule_type', function () {

        requestDependentChange($(this), '.vehicule_power', url_vehicule_change_type);
        setTimeout(function () { $('.vehicule_power').trigger('change'); }, 700);
    });

    $(document).on('change', ".vehicule_power", function () {

        requestDependentChange($(this), '.vehicule_scale', url_vehicule_change_power);
    });

    // modal new report

    $(document).on('click', '.new-report-action', function (e) {
        e.preventDefault();
        let $this = $(this);
        let $action = $this.attr('href');
        $.ajax({
            method: 'POST',
            dataType: 'html',
            url: $action,
            beforeSend: function () {
                $this.prop('disabled', 'disabled');
                $this.append(' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            },
            success: function (data) {
                $('.spinner-border').remove();
                var modalContent = $(data).find('.content-wrapper');
                modalContent.find('form').attr('action', $action);

                doModal('Créer un rapport mensuel', modalContent.html(), null); ///'style="height: 20em;"'
                $(".resizer-handler").remove();
                $("#Report_Year_month").hide().val(1);
                $("#Report_Year_day").hide().val(1);

            },
            error: function(xhrRequest) {
                if(xhrRequest.responseText !== null) {
                    var data = jQuery.parseHTML(xhrRequest.responseText);
                    $('.spinner-border').remove();
                    var modalContent = $(data).find('.content-wrapper');
                    modalContent.find('form').attr('action', $action);
    
                    doModal('Créer un rapport mensuel', modalContent.html(), null); ///'style="height: 20em;"'
                    $(".resizer-handler").remove();
                    $("#Report_Year_month").hide().val(1);
                    $("#Report_Year_day").hide().val(1);
                }
            }
        });

        $(document).on('submit', $('#dynamicModal').find('form'), function (e) {
            e.preventDefault();
            let $form = $('#dynamicModal').find('form');
            let $action = $(".new-report-action").attr('href');
            let $data = $form.serializeArray();
            let $btn = $('#dynamicModal').find('.btn-primary');
            $btn.attr('name', "NewReportAjax")
            $data.push({ name: $btn.attr('name'), value: $btn.val() });
            //console.log($btn.val());
            $.ajax({
                method: 'POST',
                dataType: 'html',
                url: $action,
                data: $data,

                success: function (html) {
                    //console.log(html);
                    if (html.indexOf("http") == 0) {
                        window.location.href = html;
                    } else {
                        $('#dynamicModal').find('.modal-body').replaceWith($(html).find('.content-wrapper'));
                        $('#dynamicModal').find('.content-wrapper').removeClass('content-wrapper').addClass('modal-body')
                        $(".resizer-handler").remove();
                        $("#Report_Year_month").hide().val(1);
                        $("#Report_Year_day").hide().val(1);
                    }
                }
            });
        });

    });

    // favories Reportline

    $(".report_favories").parent().parent().parent().css("display", "none");
    $(".report_favories").find("input:first").prop("checked", true);

    $(document).on('click', '.popup-fav-start', function (e) {
        e.preventDefault();
        let $this = $(this);

        favoriteModal($this, url_popup_fav_start, $('form').find('.lines_start'), null);

    });

    $(document).on('click', '.popup-fav-lines-start', function (e) {
        e.preventDefault();
        let $this = $(this);
        let line = $this.parent().parent().parent().parent().parent();


        favoriteModal($this, url_popup_fav_lines_start, line.find('.report_lines_start'), line);

    });

    $(document).on('click', '.popup-fav-end', function (e) {
        e.preventDefault();
        let $this = $(this);



        favoriteModal($this, url_popup_fav_end, $('form').find('.lines_end'), null);

    });

    $(document).on('click', '.popup-fav-lines-end', function (e) {
        e.preventDefault();
        let $this = $(this);
        let line = $this.parent().parent().parent().parent().parent();


        favoriteModal($this, url_popup_fav_lines_end, line.find('.report_lines_end'), line);

    });

    // copy report line 
    $(document).on('click', '.copy_link', function (e) {
        e.preventDefault();
        var inputs = $(this).parent().parent().find('input, textarea, select');
        var values = new Array;
        var checked = false;
        $.each(inputs, function (index, value) {
            if ($(value).hasClass('report_lines_is_return') && $(value).prop('checked')) {
                checked = true;
            }

            values.push($(value).val());

        });
        CopyLine(values, checked);
    });

    // footer in Report index

    $(document).on('change', '.form_scale', function (e) {
        let $obj = $(this);
        let $form = $obj.closest('form');
        let $value = $obj.val();
        let $content = '<p><i class="fa-solid fa-triangle-exclamation"></i> Le changement de barème sera appliqué au rapport annuel ainsi qu\'aux rapports provisionnels de l\'année fiscale.</p><p>Si vous souhaitez conserver une copie de vos rapports provisionnels de l\'année, cliquez sur Annuler et téléchargez-les avant d\'appliquer la modification de barème.</p>';
        $content += '<button type="button" class="btn btn-secondary do_not_change_scale" data-bs-dismiss="modal">Annuler</button>';
        $content += '<button type="button" class="btn btn-primary confirm_change_scale ms-2">Confirmer</button>';
        let $title = 'Veuillez confirmer cette action';
        doModal($title, $content, null);
        $(document).on('click', '.confirm_change_scale', function (e) {
            $(this).attr('disabled', 'disabled');
            $(this).append(' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            $form.submit();
        });
        $(document).on('click', '.do_not_change_scale', function (e) {
            $obj.val($value);
        });

    });

});

var delay = (function () {
    var timer = 0;
    return function (callback, ms) {
        clearTimeout(timer);
        timer = setTimeout(callback, ms);
    };
})();

function doModal(heading, content, height) {
    let html = '<div id="dynamicModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="title-modal" aria-hidden="true">';
    html += '<div class="modal-dialog modal-lg">';
    html += '<div class="modal-content">';
    html += '<div class="modal-header">';
    html += '<h5 class="modal-title" id="title-modal">' + heading + '</h5>';
    html += '<button type="button" class="btn btn-close" data-dismiss="modal" aria-label="Close"></button>';
    html += '</div>';
    html += '<div class="modal-body" ' + height + '>';
    html += content;
    html += '</div>';
    html += '</div>';  // body
    html += '</div>';  // content
    html += '</div>';  // modalWindow
    $('body').append(html);
    $("#dynamicModal").modal();
    $("#dynamicModal").modal('show');
    $('#dynamicModal').on('hidden.bs.modal', function (e) {
        $(this).remove();
    });
    $(document).on('click', ".btn-close", function () {
        $('#dynamicModal').modal('hide');
    });

}

function initAutocomplete() {
    var input = $('.autocomplete');
    var options = {
        componentRestrictions: { country: 'fr' }
    };

    for (var i = 0; i < input.length; i++) {
        var autocomplete = new google.maps.places.Autocomplete(input[i], options);
        autocomplete.inputId = input[i].id;
    }
}

function calculDistance(line, distance, dpt, arv) {

    if (dpt.val() && arv.val()) {
        var service = new google.maps.DistanceMatrixService();
        $("form").find('.report_km_total').val('').addClass('loading');
        $("form").find('.report_amount').val('').addClass('loading');
        //console.log(line);
        if (line) {
            line.find('.report_lines_km_total ').val('').addClass('loading');
            line.find('.report_lines_amount').val('').addClass('loading');
        }
        service.getDistanceMatrix({
            origins: [dpt.val()],
            destinations: [arv.val()],
            travelMode: google.maps.TravelMode.DRIVING,
            unitSystem: google.maps.UnitSystem.METRIC,
            avoidHighways: false,
            avoidTolls: false
        }, function (response, status) {
            if (status !== google.maps.DistanceMatrixStatus.OK) {
                alert('Error was: ' + status);
            } else {
                //console.log(response);
                for (var i = 0; i <= response.originAddresses.length; i++) {
                    if (typeof response.rows[i] !== 'undefined') {
                        var results = response.rows[i].elements;
                        for (var j = 0; j <= results.length; j++) {
                            var element = results[j];
                            if (typeof element !== 'undefined') {
                                if (typeof element.distance !== 'undefined') {
                                    if (distance.val() !== ((element.distance.value) / 1000)) {
                                        distance.val(Math.round((element.distance.value) / 1000));
                                        distance.attr('value', Math.round((element.distance.value) / 1000));
                                    }
                                }

                            }
                        }
                    }
                }
            }
        });
    }
    delay(function () {
        calculTotalLineKm(line);
    }, 800);
}

function calculTotalLineKm(line) {

    var total_km = 0;
    if (line == null) {
        var km = $('form').find(".report_km").val()
        if ($('form').find('.report_is_return').is(':checked')) {
            total_km = parseFloat(km) * 2;
        } else {
            total_km = parseFloat(km);
        }
        $("form").find('.report_km_total').removeClass('loading');
        $("form").find('.report_km_total').val(Math.round(total_km));
    } else {
        var km = line.find('.report_lines_km').val();
        if (line.find('.report_lines_is_return').is(':checked')) {
            total_km = parseFloat(km) * 2;
        } else {
            total_km = parseFloat(km);
        }
        line.find('.report_lines_km_total').removeClass('loading');
        line.find('.report_lines_km_total').val(Math.round(total_km));
    }
    requestForGeneratingAmount(line);
}

function requestForGeneratingAmount(line) {
    const params = new URLSearchParams(window.location.search)
    //console.log(params.get("entityId"));
    if (line) {
        var $total = line.find('.report_lines_amount');
        var $km = line.find('.report_lines_km_total');
        var $vehicule = line.find('.report_lines_vehicule');
        var reportId = params.get("entityId");
    } else {
        var $total = $('.report_amount');
        var $km = $("form").find('.report_km_total');
        var $vehicule = $('.report_vehicule');
        var reportLineId = params.get("entityId");
    }

    if ($km.val() != 0 && $vehicule) {


        var data = {};
        data['report_id'] = reportId;
        data['report_line_id'] = reportLineId;
        data['distance'] = $km.val();
        data['vehicule'] = $vehicule.val();
        $.ajax({
            type: 'GET',
            url: url_generateAmountAction,
            beforeSend: function () {
                $total.val('');
                $total.addClass('loading');
            },
            data: data,
            success: function (data, textStatus, XMLHttpRequest) {
                $total.removeClass('loading');
                $total.val(data);
                if (line) {
                    totalForReport();
                }
            },
            dataType: 'json'
        });
        return false;
    }
}

function requestDependentChange(obj, classToChange, action) {
    var data = {};
    data[obj.attr('name')] = obj.val();
    data["Vehicule[type]"] = $('.vehicule_type:checked').val();

    $.ajax({
        dataType: "html",
        type: 'POST',
        url: action,
        data: data,
        beforeSend: function () {
            $(classToChange).val('').addClass('loading');
        },
        success: function (html) {
            console.log(html);
            $(classToChange).parent().replaceWith($(html).find(classToChange).parent());
        },
        error: function (xhrRequest) {
            if(xhrRequest.responseText !== null) {
                var html = jQuery.parseHTML(xhrRequest.responseText);
                $(classToChange).parent().replaceWith($(html).find(classToChange).parent());
            }
        },
    });
    return false;
}

function totalForReport() {
    let total = 0;
    let totalKm = 0;

    // Calcul km 
    let kmTotal = $('form').find('.km');
    let linesKm = $('form').find('.report_lines_km_total');
    linesKm.each(function (index) {
        totalKm = totalKm + parseInt($(this).val());
    });
    kmTotal.val(totalKm);

    // Calcul Amount 

    let amountTotal = $('form').find('.total');
    let linesAmount = $('form').find('.report_lines_amount');
    linesAmount.each(function (index) {
        total = total + parseFloat($(this).val().replace(",", "."));
    });
    amountTotal.val(total);
}

function dateTypeRange() {
    let travelDate = $('form').find(".report_lines_travel_date")
    let startDate = $('form').find(".report_start_date")
    let endDate = $('form').find(".report_end_date")

    travelDate.attr("min", startDate.val());
    travelDate.attr("max", endDate.val());
}

function favoriteModal(btn, urlAjax, classToChange, line) {
    $.ajax({
        method: 'POST',
        dataType: 'html',
        url: urlAjax,
        beforeSend: function () {
            btn.prop('disabled', 'disabled');
            btn.append(' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        },
        success: function (data) {
            $('.spinner-border').remove();
            var modalContent = $(data).find('.report_favories');
            doModal('Sélectionnez une de vos adresses', modalContent.html(), null);

            $('#dynamicModal').find('.report_favories_choice').on('click', function (e) {
                classToChange.val($("#dynamicModal").find('.report_favories_choice:checked').val())
                if (line) {
                    let distance = line.find('.report_lines_km');
                    let dpt = line.find('.report_lines_start');
                    let arv = line.find('.report_lines_end');
                    console.log(distance);
                    delay(function () {
                        console.log(distance);
                        calculDistance(line, distance, dpt, arv)
                    }, 800);
                } else {
                    let distance = $("form").find('.report_km');
                    let dpt = $("form").find('.lines_start');
                    let arv = $("form").find('.lines_end');
                    delay(function () {
                        calculDistance(null, distance, dpt, arv)
                    }, 800);
                }
                $('#dynamicModal').remove();
                $('.modal-backdrop').remove();
            });

            $('#dynamicModal').find('.form-check-label').on('click', function (e) {
                classToChange.val($(this).parent().find('input').val())
                if (line) {
                    let distance = line.find('.report_lines_km');
                    let dpt = line.find('.report_lines_start');
                    let arv = line.find('.report_lines_end');
                    delay(function () {
                        calculDistance(line, distance, dpt, arv)
                    }, 800);
                } else {
                    let distance = $("form").find('.report_km');
                    let dpt = $("form").find('.lines_start');
                    let arv = $("form").find('.lines_end');
                    delay(function () {
                        calculDistance(null, distance, dpt, arv)
                    }, 800);
                }
                $('#dynamicModal').remove();
                $('.modal-backdrop').remove();
            });
        },
    });
}

function CopyLine(values, checked) {
    $('form').find('.field-collection-add-button').click()
    initAutocomplete();
    dateTypeRange();
    let lines = $('form').find('.field-collection-item');

    let line = lines.last();
    line.find('.report_lines_travel_date').val(values[0]);
    line.find('.report_lines_vehicule').val(values[1]);
    line.find('.report_lines_start').val(values[2]);
    line.find('.report_lines_end').val(values[3]);
    if (checked) {
        line.find('.report_lines_is_return').prop("checked", true);
    }
    line.find('.report_lines_km').val(values[4]);
    line.find('.report_lines_km_total').val(values[6]);
    line.find('.report_lines_comment').val(values[7]);
    line.find('.report_lines_amount').val(values[8]);

    totalForReport();
    focus(line);
}