(function (window) {
    'use strict';

    // The only PDF address a courier sends is Econt's label, so a frame may show
    // Econt's own hosts and nothing else: never code, another site, or a page of
    // the WordPress site itself.
    var HOSTS = ['econt.com'];

    function known(hostname) {
        return HOSTS.some(function (host) {
            return hostname === host || hostname.slice(-host.length - 1) === '.' + host;
        });
    }

    window.nashenskiCourierPdf = {
        url: function (value, size) {
            if (typeof value !== 'string' || /[\x00-\x20\x7f\\]/.test(value) ||
                !/^https?:\/\/[a-z0-9.-]+(?::[0-9]+)?(?:[/?#]|$)/i.test(value)) {
                return '';
            }
            try {
                var url = new URL(value);
                var hostname = url.hostname.replace(/\.$/, '').toLowerCase();
                if (url.username || url.password || !known(hostname) ||
                    hostname === window.location.hostname.replace(/\.$/, '').toLowerCase()) {
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
