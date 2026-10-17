/* Film Transfer Toolkit blocks (editor only). Plain ES5, no build step. Dynamic blocks: save() returns null and
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

  function control(a, attrs, setAttributes) {
    var val = attrs[a.name] === undefined ? '' : attrs[a.name];
    var onChange = function (v) { var o = {}; o[a.name] = v === undefined || v === null ? '' : String(v); setAttributes(o); };
    if (a.type === 'select' && a.options.length) {
      return el(C.SelectControl, { key: a.name, label: a.name, help: a.help, value: val, options: a.options, onChange: onChange });
    }
    return el(C.TextControl, { key: a.name, label: a.name, help: a.help, value: val, placeholder: a.placeholder || '', type: a.type === 'number' ? 'number' : 'text', onChange: onChange });
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
      keywords: ['film', 'transfer', 'ftt'],
      attributes: attributes,
      supports: { html: false, customClassName: false, className: false },
      example: { attributes: {} },
      transforms: { from: [{ type: 'shortcode', tag: shortcodeTags, attributes: shortcodeAttrs }] },
      edit: function (props) {
        var attrs = props.attributes;
        var blockProps = be.useBlockProps ? be.useBlockProps({ className: 'ftt-block-ph' }) : { className: 'ftt-block-ph' };
        var changed = b.atts.filter(function (a) { return attrs[a.name] !== undefined && attrs[a.name] !== '' && attrs[a.name] !== a['default']; });
        var inspector = b.atts.length ? el(be.InspectorControls, null,
          el(C.PanelBody, { title: i18n.settings, initialOpen: true },
            b.atts.map(function (a) { return control(a, attrs, props.setAttributes); }),
            el('p', null, el(C.ExternalLink, { href: b.help }, i18n.help))
          )
        ) : el(be.InspectorControls, null, el(C.PanelBody, { title: i18n.settings }, el('p', null, el(C.ExternalLink, { href: b.help }, i18n.help))));
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
