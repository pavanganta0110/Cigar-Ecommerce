/* global jQuery, wp */

jQuery(($) => {
  $(document).on('click', '.compadres-select-media', (event) => {
    event.preventDefault();
    const button = $(event.currentTarget);
    const frame = wp.media({
      title: 'Select brand media',
      library: { type: 'image' },
      multiple: false,
    });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      $(`#${button.data('target')}`).val(attachment.id).trigger('change');
    });
    frame.open();
  });
});
