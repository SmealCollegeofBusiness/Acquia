/**
 * @file
 * Content Hub Publisher Javascript Methods.
 */

(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.statusReport = {
        attach: function (context, settings) {
            let subscriber_colors;
            let publisher_colors;
            if (drupalSettings.acquia_contenthub_subscriber_color) {
                subscriber_colors = Object.values(drupalSettings.acquia_contenthub_subscriber_color);
                buildChart(drupalSettings.acquia_contenthub_subscriber_status, subscriber_colors, 'subscriber_chart', 'Subscriber Statuses');
            }
            if (drupalSettings.acquia_contenthub_publisher_color) {
                publisher_colors = Object.values(drupalSettings.acquia_contenthub_publisher_color);
                buildChart(drupalSettings.acquia_contenthub_publisher_status, publisher_colors, 'publisher_chart', 'Publisher Statuses');
            }

            function buildChart(statuses, colors, element, title) {
                let dataArray = [];
                if (statuses && statuses.hasOwnProperty('total')) {
                    delete statuses.total;

                    for (let [key, value] of Object.entries(statuses)) {
                        dataArray.push([key,Number.parseInt(value,10)])
                    }

                    dataArray.unshift(['Status', 'Total Interests']);
                }

                if (Array.isArray(dataArray) && dataArray.length) {
                    google.charts.load('current', {'packages': ['corechart']});
                    google.charts.setOnLoadCallback(function () {
                        drawChart(dataArray, colors, element, title);
                    });
                }
            }

            function drawChart(dc_dataArray, dc_colors, dc_element, dc_title) {
                let data = google.visualization.arrayToDataTable(dc_dataArray);

                let options = {
                    title: dc_title,
                    colors: dc_colors,
                    is3D: true
                };

                if (!dc_colors) {
                    delete options.colors;
                }

                let chart = new google.visualization.PieChart(document.getElementById(dc_element));

                chart.draw(data, options);
            }

        }
    };
})(jQuery, Drupal, drupalSettings);
