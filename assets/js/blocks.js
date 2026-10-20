/* FTT blocks (editor only). Plain ES5, no build step. Dynamic blocks: save() returns null and
 * the server renders each block through its shortcode handler. */
(function (wp, cfg) {
  'use strict';
  if (!wp || !wp.blocks || !cfg || !cfg.blocks) { return; }
  var el = wp.element.createElement;
  var be = wp.blockEditor || wp.editor;
  var C = wp.components;
  var i18n = cfg.i18n || {};

  function shortcodeText(b, attrs) {
    var parts = ['[' + b.shortcode];
    b.atts.forEach(function (a) {
      var v = attrs[a.name];
      if (v !== undefined && v !== '' && v !== a['default']) { parts.push(a.name + '="' + String(v).replace(/"/g, '') + '"'); }
    });
    return parts.join(' ') + ']';
  }

  // Sidebar help: what it does, its default, allowed values (free-text options) and the matching shortcode option.
  function helpText(a) {
    var t = a.help + ' ' + i18n['default'] + ' ' + a.defaultText + '.';
    if (a.type !== 'select') { t += ' ' + i18n.allowed + ' ' + a.values + '.'; }
    return t + ' ' + i18n.option + ' ' + a.name + '="…"';
  }

  function control(a, attrs, setAttributes) {
    var val = attrs[a.name] === undefined ? '' : attrs[a.name];
    var onChange = function (v) { var o = {}; o[a.name] = v === undefined || v === null ? '' : String(v); setAttributes(o); };
    var common = { key: a.name, label: a.label || a.name, help: helpText(a), value: val, onChange: onChange, className: 'ftt-ctl ftt-ctl--' + a.name, __nextHasNoMarginBottom: true };
    if (a.type === 'select' && a.options.length) {
      return el(C.SelectControl, Object.assign(common, { options: a.options }));
    }
    return el(C.TextControl, Object.assign(common, { placeholder: a.placeholder || (a['default'] || ''), type: a.type === 'number' ? 'number' : 'text', __next40pxDefaultSize: true }));
  }

  function resetAll(b, setAttributes) {
    var o = {};
    b.atts.forEach(function (a) { o[a.name] = a['default']; });
    return el(C.Button, { variant: 'secondary', onClick: function () { setAttributes(o); } }, i18n.reset);
  }

  cfg.blocks.forEach(function (b) {
    var attributes = {};
    b.atts.forEach(function (a) { attributes[a.name] = { type: 'string', 'default': a['default'] }; });

    var shortcodeTags = [b.shortcode].concat(b.aliases || []);
    var shortcodeAttrs = {};
    b.atts.forEach(function (a) {
      shortcodeAttrs[a.name] = { type: 'string', shortcode: function (sc) { var v = sc.named[a.name]; return v === undefined ? a['default'] : String(v); } };
    });

    wp.blocks.registerBlockType(b.name, {
      apiVersion: 2,
      title: b.title,
      description: b.summary,
      category: 'film-transfer-toolkit',
      icon: b.icon,
      keywords: ['film', 'transfer', 'ftt', 'toolkit'],
      attributes: attributes,
      supports: { html: false, customClassName: false, className: false },
      example: { attributes: {} },
      transforms: { from: [{ type: 'shortcode', tag: shortcodeTags, attributes: shortcodeAttrs }] },
      edit: function (props) {
        var attrs = props.attributes;
        var blockProps = be.useBlockProps ? be.useBlockProps({ className: 'ftt-block-ph' }) : { className: 'ftt-block-ph' };
        var changed = b.atts.filter(function (a) { return attrs[a.name] !== undefined && attrs[a.name] !== '' && attrs[a.name] !== a['default']; });
        var inspector = b.atts.length ? el(be.InspectorControls, null,
          el(C.PanelBody, { title: i18n.settings, initialOpen: true, className: 'ftt-panel' },
            b.atts.map(function (a) { return control(a, attrs, props.setAttributes); }),
            changed.length ? el('p', null, resetAll(b, props.setAttributes)) : null,
            el('p', null, el(C.ExternalLink, { href: b.help }, i18n.help))
          )
        ) : el(be.InspectorControls, null, el(C.PanelBody, { title: i18n.settings, className: 'ftt-panel' }, el('p', null, i18n.none), el('p', null, el(C.ExternalLink, { href: b.help }, i18n.help))));
        return el('div', blockProps,
          inspector,
          el('div', { className: 'ftt-block-ph__head' },
            el('span', { className: 'dashicons dashicons-' + b.icon, 'aria-hidden': 'true' }),
            el('strong', null, b.title)
          ),
          el('img', { className: 'ftt-block-ph__img', src: b.image, alt: '' }),
          el('p', { className: 'ftt-block-ph__note' }, i18n.note),
          el('p', { className: 'ftt-block-ph__sc' }, i18n.shortcode + ': ', el('code', null, shortcodeText(b, attrs))),
          changed.length ? null : el('p', { className: 'ftt-block-ph__defaults' }, b.atts.length ? i18n.defaults : '')
        );
      },
      save: function () { return null; }
    });
  });
})(window.wp, window.FTT_BLOCKS);
