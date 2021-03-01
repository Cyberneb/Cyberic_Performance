/**
 * @author Eric Allatt <eric@cyberic.net>
 * @link https://www.cyberic.net
 */
define([], function () {
    return {
        /**
         * Sets instance variables
         *
         * @param {string} route - Current page route
         * @param {string} url - Dependencies action URL
         */
        init(route, url) {
            this.route = route;
            this.url = url;
        },
        /**
         * Checks for flag and triggers dependency retrieval
         */
        run() {
            const searchParams = new URLSearchParams(window.location.search);
            if (searchParams.has('retrieve_deps')) {
                document.cookie="retrieve_deps=1;SameSite=Strict";
            }
            if (document.cookie.split(';').some((item) => item.includes('retrieve_deps=1'))) {
                setTimeout(() => this.doRequest(), 5000);
            }
        },
        /**
         * Posts RequireJS dependency data
         */
        doRequest() {
            const { paths } = require.s.contexts._.config;
            const data = {
                route: this.route,
                deps: this.getDeps(),
                paths,
            };
            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.url);
            xhr.setRequestHeader('Content-type', 'application/json;charset=utf-8');
            xhr.send(JSON.stringify(data));
        },
        /**
         * Gets RequireJS JavaScript dependencies
         *
         * @returns {Array} All JavaScript dependencies
         */
        getJsDeps() {
            const { config: { baseUrl }, urlFetched } = require.s.contexts._;
            return Object.keys(urlFetched).map((module) => module.replace(baseUrl, ''));
        },
        /**
         * Gets RequireJS HTML dependencies
         *
         * @returns {Array} All HTML dependencies
         */
        getHtmlDeps() {
            const { defined } = require.s.contexts._;
            return Object.keys(defined).reduce((deps, module) => {
                if (module.match(/^text!.+\.html$/)) {
                    deps.push(module.replace(/^text!/, ''));
                }
                return deps;
            }, []);
        },
        /**
         * Gets RequireJS dependencies
         *
         * @returns {Array} All dependencies
         */
        getDeps() {
            return [...this.getJsDeps(), ...this.getHtmlDeps()];
        }
    }
});
