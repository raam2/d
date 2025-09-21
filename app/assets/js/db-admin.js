document.addEventListener("DOMContentLoaded", function () {
    const table = document.getElementById("data-table");
    if (!table) return;

    const filterInputs = document.querySelectorAll(".column-filter-input");
    const activeFilters = {};

    filterInputs.forEach(input => {
        const columnName = input.dataset.column;
        const tableName = input.dataset.table;
        const dbName = input.dataset.db;

        activeFilters[columnName] = [];

        new TomSelect(input, {
            plugins: ['remove_button'],
            valueField: 'value',
            labelField: 'value',
            searchField: 'value',
            preload: 'focus',
            create: false,
            // Fetch distinct values for this column from our new API endpoint
            load: function (query, callback) {
                const url = `?action=get_distinct_values&db=${dbName}&table=${tableName}&column=${columnName}&q=${encodeURIComponent(query)}`;
                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        callback(json.items);
                    }).catch(() => {
                        callback([]);
                    });
            },
            // When the user changes a filter, update the table
            onChange: function (values) {
                activeFilters[columnName] = values;
                filterTableRows();
            }
        });
    });

    function filterTableRows() {
        const tableRows = table.querySelector("tbody").querySelectorAll("tr");
        
        tableRows.forEach(row => {
            let visible = true;
            const cells = row.querySelectorAll("td");

            for (const [columnIndex, cell] of cells.entries()) {
                const columnName = table.querySelector(`thead tr.columns-row th:nth-child(${columnIndex + 1})`).dataset.column;
                const filterValues = activeFilters[columnName];
                
                if (filterValues && filterValues.length > 0) {
                    if (!filterValues.includes(cell.textContent)) {
                        visible = false;
                        break; // This row doesn't match, no need to check other columns
                    }
                }
            }
            
            // Show or hide the row based on whether it passed all active filters
            row.style.display = visible ? "" : "none";
        });
    }
});
