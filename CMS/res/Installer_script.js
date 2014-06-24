jQuery(function($){
    var $installer = $('#installer');

    $installer.find('.cms_expander').click(function(event){
        var button = $(this);

        button.hide();
        button.next('.cms_expandable').show();
        button.prevAll('.cmd_expand_shy').hide();
    });

    $('#installer_go').click(function(event) {
        $installer.children('h2').text('Set up DB');
    });

//    $('#installer_connect').click();

    function fetchDBList() {
        var form = $installer.find('form');

        var sendData = {
            install_test: 1,
            host: form.find("input[name='site.db.host']").val(),
            username: form.find("input[name='site.db.username']").val(),
            password: form.find("input[name='site.db.password']").val()
        };

        $.ajax({
            url: document.location,
            method: 'POST',
            data: sendData,
            dataType: 'json',
            success: function (data, textStatus, jqXHR) {
                if (data && data.connected) {
                    updateDBList(data.dbs);
                } else {
                    testConnectionFailed('Website connected, DB not connected', data);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                testConnectionFailed('Website not connected', {textStatus:textStatus,errorThrown:errorThrown});
            }
        });

//                $('#installer_test').prop('disabled',false);
//                $('#install_run').prop('disabled',false);
//                $('#section_install').show();
        $('#test_connection_status').text('...testing...');
        $('#test_connection_output').empty();
    }

    function jsonScalar(test) {
        var t = typeof test;
        return t==='string'||t==='number'||t==='boolean'||(test===null)
    }

    function jsonToHTML(object, maxDepth) {
        var out, value, names, n, key, nextDepth = maxDepth-1;

        if (object == null) {
            out = 'NULL';
        } else if (jsonScalar(object)) {
            out = $('<pre>').text(object);
        } else if (typeof object === 'object') {

            out = $('<table>',{'class':'object_table'});

            var numKeys = $.isArray(object);
            if (numKeys) {
                names = object;
            } else {
                names = [];
                for (key in object) if (object.hasOwnProperty(key)) {
                    names.push(key);
                }
                names.sort(function (a, b) {
                    return a.localeCompare(b);
                });
            }

            for (n = 0; n < names.length; ++n) {
                key = numKeys ? n : names[n];
                if (nextDepth >= 0) {
                    value = jsonToHTML(object[key], nextDepth);
                } else {
                    value = '...abridged...';
                }

                out.append($('<tr>').append(
                    $('<th>').text(key),
                    $('<td>').append(value)
                ));
            }
        }
        return out;
    }

    function testConnectionFailed(reason, data) {
        var content = $('<div>').text(reason);

        if (data) {
            content.append(jsonToHTML(data,2));
        }

        $('#test_connection_status').text('Error');
//        .tooltip({ items:'*', content: content.html() });
        $('#test_connection_output').append(content);

        $('#installer_test').prop('disabled',false);
        $('#install_run').prop('disabled',true);
        $('#section_install').hide();
    }

    function updateDBList(dbs) {
        var labels = [], names = [], nameLookup = {};

        if (!dbs.framework_cms) {
            labels.push({label:'CREATE: framework_cms',value:'framework_cms'});
            nameLookup.framework_cms = 'framework_cms';
        }

        for (var db in dbs) if (dbs.hasOwnProperty(db)) {
            names.push(db);
            nameLookup[db] = db;
        }

        names.sort(function (a, b) {
            return a.localeCompare(b);
        });

        for (var i = 0; i < names.length; ++i) {
            db = names[i];
            var info = dbs[db];
            var type = !info.access ? 'NO ACCESS' : (info.occupied ? 'IN USE' : 'EMPTY');

            labels.push({label:type+': '+db, value:db});
        }

//                alert('DBs: '+names.join(', '));

        $installer.find('input[name="site.db.db_name"]').autocomplete({
            delay: 0,
            minLength: 0,
            source: function(request,response) {
                var userValue = request.term;
                var userTokens;

                if (userValue && userValue.length && !nameLookup.hasOwnProperty(userValue)) {
                    userTokens = [
                        {label: 'CREATE: ' + userValue, value: userValue}
                    ];
                } else {
                    userTokens = [];
                }

                response(userTokens.concat(labels));
            }
        }).on('focus click',function() {
            $(this).autocomplete('search')
        });

        $('#installer_test').prop('disabled',false);
        $('#install_run').prop('disabled',false);
        $('#section_install').show();
        $('#test_connection_status').text('OK');
        $('#test_connection_output').empty();
    }

    function attemptInstall() {
        // Use visibility not display to leave UI in place
        $('#section_test').css('visibility','hidden');

        var form = $installer.find('form');

        var username = form.find("input[name='site.db.username']").val();
        var db_name = form.find("input[name='site.db.db_name']").val();

        // should validate username.length && db_name.length

        var sendData = {
            install_run: 1,
            host: form.find("input[name='site.db.host']").val(),
            username: username,
            password: form.find("input[name='site.db.password']").val(),
            db_name: db_name
        };

        $.ajax({
            url: document.location,
            method: 'POST',
            data: sendData,
            dataType: 'json',
            success: function (data, textStatus, jqXHR) {
                if (data && data.install_complete) {
                    installComplete(data);
                } else {
                    installFailed('Install not successful', data);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var responseText, sent = false;
                try {
                    responseText = jqXHR.responseText;

                    if (responseText.length
                        && (responseText.charAt(0) === "{")
                        && (responseText.charAt(responseText.length-1) === "}")
                    ) {
                        var data = JSON.parse(responseText);

                        if (typeof data === 'object') {
                            installFailed('Install not successful', data);
                            sent = true;
//                        } else {
//                            installFailed('Parse not successful', {textStatus:textStatus,errorThrown:errorThrown,match1:responseText.charAt(0),match2:responseText.charAt(responseText.length-1)});
                        }
                    } else {
//                        installFailed('Invalid response', {textStatus:textStatus,errorThrown:errorThrown,responseText: responseText,match1:responseText.charAt(0),match2:responseText.charAt(responseText.length-1)});
                    }
                } catch (error) {
                    installFailed('Response handler exception', {error:error,textStatus:textStatus,errorThrown:errorThrown,responseText: responseText});
                    sent = true;
                }

                if (!sent) {
                    installFailed('Website connection problem', {textStatus: textStatus, errorThrown: errorThrown, responseText: responseText});
                }
            }
        });

        $('#install_run').prop('disabled',true);
        $('#installer_output').empty().text('Installing...');

    }

    function installComplete(data) {
        $('#installer_output').empty().append(
            $('<div>',{'class':'installer_success'})
                .text('CMS has been installed.')
                .append(jsonToHTML(data,3))
        );

        $('#install_run').hide();
    }
    function installFailed(message, data) {
        $('#installer_output').empty().append(
            $('<div>',{'class':'installer_error'})
                .text(message)
                .append(jsonToHTML(data,3))
        );

        $('#install_run').prop('disabled',false);
        $('#section_test').css('visibility','visible');
    }

    $('#installer_connect').click(function(event){
        var button = $(this);

        fetchDBList();
    });

    $('#installer_test').click(function(event){
        var button = $(this);

        fetchDBList();
    });

    $('#install_run').click(function(event){
        var button = $(this);

        attemptInstall();
    });

});