/**
 * @file
 * Acquia ContentHub Dashboard.
 */

(function ($) {
  var angular_app = drupalSettings.acquia_contenthub_dashboard.angular_app;
  if (angular_app !== null) {
    var receiver = document.getElementById('acquia-contenthub-dashboard').contentWindow
    if (receiver) {
      drupalSettings.acquia_contenthub_dashboard.start_route = window.location.hash;
      receiver.postMessage(drupalSettings.acquia_contenthub_dashboard, angular_app);
    }
  }
  window.onpageshow = function (event) {
    const reload = event.persisted ||
      (typeof window.performance != "undefined" &&
        String(window.performance.getEntriesByType("navigation")[0].type) === "back_forward" );
    if (reload) {
      window.location.reload();
    }
  };
})(jQuery, drupalSettings);
