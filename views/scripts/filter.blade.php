@php use Seiger\sCommerce\Facades\sCommerce; @endphp
<script>
    document.addEventListener("click", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target.closest('[data-sFilter]')?.hasAttribute("data-sFilter")):
                    let dataFilter = e.target.closest('[data-sFilter]').getAttribute('data-sFilter');
                    generateFilterUrl(e, dataFilter);
                    break;
                case Boolean(e.target.closest('[data-sRange]')?.hasAttribute("data-sRange")):
                    let dataRange = e.target.closest('[data-sRange]').getAttribute('data-sRange');
                    const rangeBlocks = e.target.closest('[data-sRange]').querySelectorAll('input[type="number"]');
                    const rangeValues = Array.from(rangeBlocks).map(input => parseInt(input.value));
                    dataRange = dataRange + '=' + Math.min(...rangeValues) + ',' + Math.max(...rangeValues);
                    generateFilterUrl(e, dataRange, 'range');
                    break;
            }
        }
    });
    function parseCurrentFilterPath(pathname, suffix = '') {
        const normalizedPath = suffix && pathname.endsWith(suffix)
            ? pathname.slice(0, -suffix.length)
            : pathname;
        const segments = normalizedPath.split('/').filter(Boolean);
        const filterSegments = [];

        while (segments.length && segments[segments.length - 1].includes('=')) {
            filterSegments.unshift(segments.pop());
        }

        return {
            basePath: `/${segments.join('/')}`.replace(/\/+/g, '/'),
            filters: filterSegments.join(';')
        };
    }
    function normalizeFilterEntries(filters) {
        return filters
            .map(filter => {
                const [key, values = ''] = filter.split('=');
                const normalizedValues = [...new Set(values.split(',').filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b, undefined, {numeric: true, sensitivity: 'base'}));

                return normalizedValues.length ? `${key}=${normalizedValues.join(',')}` : null;
            })
            .filter(Boolean)
            .sort((a, b) => {
                const [keyA] = a.split('=');
                const [keyB] = b.split('=');
                return keyA.localeCompare(keyB, undefined, {numeric: true, sensitivity: 'base'});
            });
    }
    function generateFilterUrl(e, dataFilter, type = 'checkbox') {
        let searchFilter = true;

        const suffix = '{{evo()->getConfig('friendly_url_suffix', '')}}';
        const currentUrl = new URL(window.location.href);
        const parsedPath = parseCurrentFilterPath(currentUrl.pathname, suffix);
        const _getParams = currentUrl.search;
        const _path = parsedPath.basePath;
        const _filterMatch = parsedPath.filters;

        if (_filterMatch) {
            let existingFilters = _filterMatch.split(';');
            const [filterKey, filterValue] = dataFilter.split('=');

            existingFilters = existingFilters.map(existingFilter => {
                const [key, values] = existingFilter.split('=');
                if (key === filterKey) {
                    searchFilter = false;
                    let valueArray = values.split(',');

                    if (valueArray.includes(filterValue)) {
                        valueArray = valueArray.filter(v => v !== filterValue);
                    } else if (type === 'checkbox') {
                        valueArray.push(filterValue);
                    } else if (type === 'radio' || type === 'range') {
                        valueArray = [filterValue];
                    } else {
                        valueArray.push(filterValue);
                    }

                    return valueArray.length > 0 ? `${key}=${[...new Set(valueArray)].join(',')}` : null;
                }
                return existingFilter;
            }).filter(Boolean);

            if (searchFilter) {
                existingFilters.push(dataFilter);
            }

            existingFilters = normalizeFilterEntries(existingFilters);

            const newPath = existingFilters.length > 0
                ? `${_path}/${existingFilters.join(';')}/`
                : `${_path}/`;
            currentUrl.pathname = newPath.replace('//', '/').replace(/\/;+/g, '/').replace(/;+\//g, '/');
        } else {
            const normalizedFilters = normalizeFilterEntries([dataFilter]);
            currentUrl.pathname = `${_path}/${normalizedFilters.join(';')}/`.replace('//', '/').replace(/\/;+/g, '/').replace(/;+\//g, '/');
        }

        window.location.href = `${currentUrl.pathname}${_getParams}`;
    }
</script>
