(function (window) {
    'use strict';

    // Courier/CDN hosts can vary. Require an absolute remote web URL, never code
    // or a navigation back into the authenticated WordPress site's origin.
    window.nashenskiCourierPdf = {
        url: function (value, size) {
            if (typeof value !== 'string' || /[\x00-\x20\x7f\\]/.test(value) ||
                !/^https?:\/\/[a-z0-9.-]+(?::[0-9]+)?(?:[/?#]|$)/i.test(value)) {
                return '';
            }
            try {
                var url = new URL(value);
                if (url.username || url.password || !url.hostname ||
                    url.hostname.replace(/\.$/, '').toLowerCase() === window.location.hostname.replace(/\.$/, '').toLowerCase()) {
                    return '';
                }
                // The old scheme-stripping code also upgraded HTTP courier links
                // on HTTPS admin pages; retain that compatibility explicitly.
                url.protocol = 'https:';
                if (typeof size === 'string') {
                    url.searchParams.set('label', size);
                }
                return url.href;
            } catch (error) {
                return '';
            }
        }
    };
}(window));
