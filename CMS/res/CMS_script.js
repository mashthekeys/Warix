jQuery(function($) {
    var DEBUG = {
        appendAJAXJunk: 10, // 10 MB junk data on every request!
        showPHPErrors: true
    };

//    var baseUrl = $('head').children('base').prop('href');
//    if (!baseUrl) {
//        baseUrl = '/';
//    } else if (baseUrl.charAt(baseUrl.length-1) !== '/') {
//        baseUrl += '/';
//    }

    var baseUrl = ( $('<a>',{href:'./'}).prop('href') );

    var ajaxUrl = baseUrl + '!%7B%7D';
    var lang = $('html').attr('lang');

    function xhrProgressTracker(uploadProgress,downloadProgress) {
        return function() {
            var xhr = new window.XMLHttpRequest();

            xhr.upload.addEventListener('progress', uploadProgress, false);
            xhr.addEventListener('progress', downloadProgress, false);

            return xhr;
        }
    }

    function progressBarUpdater($progress) {
        return function (completeRatio) {
            if (0 <= completeRatio && completeRatio <= 1) {
                $progress.val(completeRatio);
            } else if (completeRatio > 1) {
                $progress.val(1);
            } else {
                $progress.removeProp('value');
            }
        }
    }

    function objectToUrl(object) {
        var str = '';
        for (var key in object) if (object.hasOwnProperty(key)) {
            str += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(object[key]);
        }
        return str.substr(1);
    }

    function bgSubmit($button, $form) {
        var now = new Date(),
            command = $button.val(),
            data = $form.serialize(),
            actionUrl = $form.attr('action'),
            actionUid = 'action-' + now.getTime() + '--' + Math.random().toFixed(8).substr(2);

        data = objectToUrl({
            actionUrl: actionUrl,
            actionCommand: command,
            actionUid: actionUid
        }) + '&' + data;

        if (DEBUG.appendAJAXJunk) {
            if ($.isFunction(String.prototype.repeat)) {
                data += '&'.repeat(1024*1024*(DEBUG.appendAJAXJunk));
            } else {
                var append = '&&&&&&&&&&&&&&&&'; // 16 & = 2^4

                // 1024 & = 2^10
                // 1M   & = 2^20

                for (var pow = 4; pow < 20; ++pow) {
                    append += append;
                }
                for (var mult = 1; mult <= DEBUG.appendAJAXJunk; ++mult) {
                    data += append;
                }

                append = null;
            }
        }

        var requestHistory = $form.data('cms_request_history');

        if (!requestHistory || typeof requestHistory !== 'object') {
            requestHistory = {
                info: {},
                uids: []
            };
        }

        $button.prop('disabled', true);
        $button.data('cms_request', {actionUid: actionUid});
        $button.addClass('cms_in_progress');

        var successFn = function (data, textStatus, jqXHR) {
            if (!data) {
                errorFn(jqXHR, "cms_error", 'Garbled response');

            } else if (data.ok) {
                $button.prop('disabled', false);
                $button.removeClass('cms_in_progress');
                $button.removeData('cms_request');

                var requestHistory = $form.data('cms_request_history');

                var now = new Date();
                var message = "SUCCESS\n" + now.getHours() + ":" + now.getMinutes();

                requestHistory.info[actionUid].uploadProgress = 100;
                requestHistory.info[actionUid].downloadProgress = 100;
                requestHistory.info[actionUid].actionResult = true;
                requestHistory.info[actionUid].actionStatus = message;
//                requestHistory.info[actionUid].debugData = JSON.stringify(data,null,4);

                $form.data('cms_request_history', requestHistory);

                refreshRequestList($form);
            } else {
                errorFn(jqXHR, "cms_error", "Request was not successful");
            }
        };
        // textStatus: "timeout", "error", "abort", "parsererror", or "cms_error"
        var errorFn = function (jqXHR, textStatus, errorThrown) {
            // TODO should react differently to abort and timeout

            $button.prop('disabled', false);
            $button.removeClass('cms_in_progress');
            $button.removeData('cms_request');

            var error = textStatus == null ? 'null_error' : textStatus;

            if (errorThrown) {
                error += ": "+errorThrown;
            }

            if (jqXHR.responseJSON) {
                error += "\nDetails: "+jqXHR.responseJSON.error;
                error += "\n         "+jqXHR.responseJSON.reason;
                error += "\n         "+jqXHR.responseJSON.error_details;

                if (DEBUG.showPHPErrors) {
                    error += "\n         "+jqXHR.responseJSON.php_error;
                }

            }

//            alert(JSON.stringify(data, null, 4));

            var requestHistory = $form.data('cms_request_history');

            requestHistory.info[actionUid].uploadProgress = 100;
            requestHistory.info[actionUid].downloadProgress = 100;
            requestHistory.info[actionUid].actionResult = false;
            requestHistory.info[actionUid].actionStatus = error;

            $form.data('cms_request_history',requestHistory);

            refreshRequestList($form);
        };

        var jqXHR = $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: data,
            dataType: 'json',
            xhr: xhrProgressTracker(function(completeRatio) {
                if (0 <= completeRatio && completeRatio <= 1) {
                    var pct = Math.floor(completeRatio) ? 100 : Math.floor(99 * completeRatio);
                    var requestHistory = $form.data('cms_request_history');
                    requestHistory.info[actionUid].uploadProgress = pct;
                    refreshRequestList($form);
                }
            },function(completeRatio) {
                if (0 <= completeRatio && completeRatio <= 1) {
                    var pct = Math.floor(completeRatio) ? 100 : Math.floor(99 * completeRatio);
                    var requestHistory = $form.data('cms_request_history');
                    requestHistory.info[actionUid].downloadProgress = pct;
                    refreshRequestList($form);
                }
            }),
            success: successFn,
            error: errorFn
//            complete: completeFn
        });

        requestHistory.uids.push(actionUid);
        requestHistory.info[actionUid] = {
            actionUrl: actionUrl,
            actionUid: actionUid,
            actionCommand: command,
            actionLabel: $button.text(),
            uploadProgress: 0,
            downloadProgress: 0,
            jqXHR: jqXHR
        };

        $form.data('cms_request_history',requestHistory);

//        $form.trigger('cms_bg_submit');
        refreshRequestList($form);
    }

    function toggleRequestList($form) {
        var requestArea = $form.find("div.cms_requests"),
            requestList = requestArea.children('ul');

        if (requestList.length && requestList.is(':visible')) {
            requestList.hide();
        } else {
            refreshRequestList($form);
        }
    }

    function refreshRequestList($form) {
        var requestArea = $form.find("div.cms_requests"),
            requestList = requestArea.children('ul');

        if (requestList.length) {
            requestList.show();
        } else {
            requestList = $('<ul>');
            $form.find("div.cms_requests").append(requestList);
        }

        var requestHistory = $form.data('cms_request_history');

        if (requestHistory != null && $.isArray(requestHistory.uids)) {
            var N = requestHistory.uids.length;

            for (var n = 0; n < N; ++n) {
                var actionUid = requestHistory.uids[n];
                var actionInfo = requestHistory.info[actionUid];
                var display = requestList.children('#cms_uid_'+actionUid);

                if (!display.length) {
                    requestList.append(
                        $('<li>',{'id':'cms_uid_'+actionUid}).append(
                            $('<span>',{'class':'task'}).text(actionInfo.actionLabel).append(
                                $('<progress>').val(actionInfo.uploadProgress)
                            ),
                            $('<span>',{'class':'result'}).append(
                                $('<progress>').val(actionInfo.downloadProgress)
                            )
                        )
                    );
                } else {
//                    display.attr('title','');
                    display.children('.task').children('progress').val(actionInfo.uploadProgress);
                    display.children('.result').children('progress').val(actionInfo.downloadProgress);

                    if (actionInfo.downloadProgress == 100) {
                        if (actionInfo.actionResult === false) {
                            display.children('.result').addClass('error')
                                .attr("title",actionInfo.actionStatus);

                        } else if (actionInfo.actionResult === true) {
                            display.children('.result').addClass('success')
                                .attr("title",actionInfo.actionStatus);
                        }
                    }
                }
            }

//            requestArea.children("button[name='cms_requests']").css('visibility', N ? 'visible' : 'hidden');
            requestArea.children("button[name='cms_requests']").prop('disabled', N == 0);
        }
    }

    var toolbarGroups = [
        { name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
//        { name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
        { name: 'links' },
        { name: 'insert' },
//        { name: 'forms' },
//        { name: 'tools',    groups: [ 'mode' ] },
        { name: 'mode',    groups: [ 'tools' ] },
//        { name: 'document',    groups: [ 'mode', 'document', 'doctools' ] },
//        { name: 'others' },
        '/',
        { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
        { name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align' ] },
        '/',
        { name: 'styles' },
        { name: 'colors' }
//        { name: 'about' }
    ];
    var editorStylesheets = [
        '//fonts.googleapis.com/css?family=Comfortaa:700,400|Audiowide|Anonymous+Pro:400,700&subset=latin,latin-ext'
//        'res/CMS_style.css'
    ];

    var content_html = $('textarea.content_html'),
        content_htmlSource = content_html.filter('.content_htmlDocument'),
        content_htmlOnly = content_html.not('.content_htmlDocument');

    var ckOptions = {
        toolbarGroups: toolbarGroups,
        baseHref: baseUrl,
        inline: true,
        uiColor: '#7799FF',
        contentsCss: editorStylesheets,
        customConfig: '',
        entities: true,
//        indentClasses: '...',
//        justifyClasses: '...',
        language: lang,
//        templates: '',
//        templates_files: {},
        toolbarCanCollapse: true,
        useComputedState: true
    };

    content_htmlOnly.ckeditor(ckOptions);

    ckOptions.fullPage = true;
    ckOptions.startupMode = 'source';
    ckOptions.height = 500;
    content_htmlSource.ckeditor(ckOptions);

//    ckOptions.startupMode = 'wysiwyg';

    $('div.cms_toolbar').each(function(index,toolbar) {
        var $toolbar = $(toolbar),
            $buttons = $toolbar.find("button[name='actionCommand']"),
            $form = $toolbar.closest('form');

        $buttons.prop('type','button');
        $buttons.on('click.cms_toolbar', function() { bgSubmit($(this), $form); });

        var requestArea = $toolbar.find("div.cms_requests");

        if (!requestArea.length) {
            requestArea = $("<div>",{'class':'cms_requests'}).append(
                $('<button type="button">',{name:'cms_requests'}).text('...').on('click.cms_requests',function() { toggleRequestList($form); })
            );

            $toolbar.prepend(requestArea);
        }

//        requestArea.children("button[name='cms_requests']").css('visibility', 'hidden');
        requestArea.children("button[name='cms_requests']").prop('disabled', true);

//replaced event with direct call        $form.on('cms_bg_submit', function() { refreshRequestList($form); });
    });


});