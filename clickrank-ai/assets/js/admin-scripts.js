/**
 * All admin-facing JavaScript for the ClickRank.ai plugin.
 *
 * @link       https://clickrank.ai/
 * @since      3.0.0
 *
 * @package    ClickRank_AI
 */

(function ($) {
  'use strict';

  $(function () {
    /**
     * Handles the "Toggle All" functionality on the settings page.
     */
    const masterToggle = document.getElementById('cr-master-toggle');
    const moduleToggles = document.querySelectorAll('.cr-module-toggle');

    if (masterToggle && moduleToggles.length > 0) {
      // Function to update the master toggle's state based on module toggles.
      const updateMasterToggleState = () => {
        const allChecked = [...moduleToggles].every((toggle) => toggle.checked);
        masterToggle.checked = allChecked;
      };

      // Set initial state of master toggle on page load.
      updateMasterToggleState();

      // Add event listener for the master toggle.
      masterToggle.addEventListener('change', function () {
        moduleToggles.forEach((toggle) => {
          toggle.checked = this.checked;
        });
      });

      // Add event listeners for each module toggle to update the master.
      moduleToggles.forEach((toggle) => {
        toggle.addEventListener('change', updateMasterToggleState);
      });
    }
  });
})(jQuery);
