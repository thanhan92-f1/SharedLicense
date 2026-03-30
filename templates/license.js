/*! SharedLicense HostBill Module
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

$(function () {
    var root = $('#sharedlicense-data');

    if (!root.length) {
        return;
    }

    var accountId = $('#account_id').val();
    var loaderSrc = typeof template_dir !== 'undefined' ? template_dir + 'img/ajax-loading.gif' : '';
    var loaderHtml = loaderSrc ? '<div style="text-align: center"><img src="' + loaderSrc + '"/></div>' : '<div style="text-align: center; padding: 10px 0;">Loading...</div>';

    function loadLicense(refresh) {
        root.html(loaderHtml);

        var payload = {
            id: accountId
        };

        if (refresh) {
            payload.refresh = 1;
        }

        $.post('?cmd=sharedlicense&action=license', payload).done(function (data) {
            data = parse_response(data);
            root.html(data);
        }).fail(function () {
            root.text('Failed to load license details');
        });
    }

    function copyText(text, callback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (callback) {
                    callback();
                }
            }).catch(function () {
                window.prompt('Copy command:', text);
                if (callback) {
                    callback();
                }
            });
            return;
        }

        window.prompt('Copy command:', text);
        if (callback) {
            callback();
        }
    }

    loadLicense(false);

    root.on('click', 'a.sl-change-ip', function () {
        var form = $('#sharedlicense-changeip-form');
        form.bootboxform();
        form.trigger('show');
        return false;
    });

    root.on('click', 'a.sl-renew', function () {
        var form = $('#sharedlicense-renew-form');
        form.bootboxform();
        form.trigger('show');
        return false;
    });

    root.on('click', 'a.sl-reset-ip-count', function () {
        var form = $('#sharedlicense-reset-ip-count-form');
        form.bootboxform();
        form.trigger('show');
        return false;
    });

    root.on('click', 'a.sl-refresh-details', function () {
        loadLicense(true);
        return false;
    });

    root.on('click', 'a.sl-copy-command', function () {
        var card = $(this).closest('.sl-command-card');
        var feedback = card.find('.sl-copy-feedback');
        copyText(card.find('code').text(), function () {
            feedback.stop(true, true).fadeIn(120).delay(1200).fadeOut(250);
        });
        return false;
    });
});
