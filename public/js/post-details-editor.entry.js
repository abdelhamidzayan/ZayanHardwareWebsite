import { EditorState } from '@codemirror/state';
import { EditorView, Decoration, ViewPlugin, WidgetType, keymap } from '@codemirror/view';
import { markdown } from '@codemirror/lang-markdown';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';

class RuleWidget extends WidgetType {
  toDOM() {
    const node = document.createElement('span');
    node.className = 'cm-live-preview-rule';
    node.setAttribute('aria-hidden', 'true');
    return node;
  }
}

function decorationsFor(view) {
  const ranges = [];
  let inFence = false;
  for (let n = 1; n <= view.state.doc.lines; n += 1) {
    const line = view.state.doc.line(n);
    const value = line.text;
    const fence = /^\s*```/.test(value);
    if (fence) {
      ranges.push(Decoration.replace({}).range(line.from, line.to));
      ranges.push(Decoration.line({ class: 'cm-live-preview-fence' }).range(line.from));
      inFence = !inFence;
      continue;
    }
    if (inFence) {
      ranges.push(Decoration.line({ class: 'cm-live-preview-code-line' }).range(line.from));
      continue;
    }
    const heading = /^(#{1,6})\s+(.*)$/.exec(value);
    const quote = /^>\s?/.exec(value);
    const bullet = /^\s*[-*+]\s+/.exec(value);
    const ordered = /^\s*\d+[.)]\s+/.exec(value);
    if (/^\s*(?:---+|___+|\*\*\*+)\s*$/.test(value)) {
      ranges.push(Decoration.replace({ widget: new RuleWidget() }).range(line.from, line.to));
      continue;
    }
    if (heading) {
      const prefix = heading[1].length + 1;
      ranges.push(Decoration.replace({}).range(line.from, line.from + prefix));
      if (line.to > line.from + prefix) ranges.push(Decoration.mark({ class: 'cm-live-preview-heading cm-live-preview-h' + heading[1].length }).range(line.from + prefix, line.to));
    } else if (quote) {
      ranges.push(Decoration.replace({}).range(line.from, line.from + quote[0].length));
      ranges.push(Decoration.line({ class: 'cm-live-preview-quote' }).range(line.from));
    } else if (bullet || ordered) {
      const prefix = bullet || ordered;
      ranges.push(Decoration.replace({}).range(line.from, line.from + prefix[0].length));
      ranges.push(Decoration.line({ class: 'cm-live-preview-list' }).range(line.from));
    }
    const inline = /\*\*(.*?)\*\*|`(.*?)`/g;
    let match;
    while ((match = inline.exec(value)) !== null) {
      const token = match[0];
      const width = token.startsWith('**') ? 2 : 1;
      const from = line.from + match.index;
      ranges.push(Decoration.replace({}).range(from, from + width));
      ranges.push(Decoration.replace({}).range(from + token.length - width, from + token.length));
      if (token.length > width * 2) ranges.push(Decoration.mark({ class: width === 2 ? 'cm-live-preview-bold' : 'cm-live-preview-inline-code' }).range(from + width, from + token.length - width));
    }
  }
  return Decoration.set(ranges, true);
}

const livePreview = ViewPlugin.fromClass(class {
  constructor(view) { this.decorations = decorationsFor(view); }
  update(update) {
    if (update.docChanged || update.selectionSet || update.viewportChanged) this.decorations = decorationsFor(update.view);
  }
}, { decorations: value => value.decorations });

function syncToolbar(view, toolbar) {
  if (!toolbar) return;
  const line = view.state.doc.lineAt(view.state.selection.main.head);
  const value = line.text;
  let inFence = false;
  for (let n = 1; n <= line.number; n += 1) {
    if (/^\s*\x60{3}/.test(view.state.doc.line(n).text)) inFence = !inFence;
  }
  const states = {
    bold: /^(?:\s*(?:[-*+]|\d+[.)])\s+|>\s?|#{1,6}\s+)?\*\*.*\*\*$/.test(value),
    heading: /^#{1,6}\s+/.test(value),
    bulleted: /^\s*[-*+]\s+/.test(value),
    numbered: /^\s*\d+[.)]\s+/.test(value),
    quote: /^>\s?/.test(value),
    inline_code: /^(?:\s*(?:[-*+]|\d+[.)])\s+|>\s?|#{1,6}\s+)?\x60.*\x60$/.test(value),
    code_block: inFence,
    divider: /^\s*(?:---+|___+|\*\*\*+)\s*$/.test(value)
  };
  toolbar.querySelectorAll('[data-format]').forEach(button => {
    const active = Boolean(states[button.dataset.format]);
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
}

function continueList(view) {
  const selection = view.state.selection.main;
  if (!selection.empty) return false;
  const line = view.state.doc.lineAt(selection.head);
  const list = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/.exec(line.text);
  const tick = String.fromCharCode(96);

  if (list) {
    if (!list[3].trim()) {
      view.dispatch({
        changes: { from: line.from, to: line.to, insert: '' },
        selection: { anchor: line.from },
        scrollIntoView: true
      });
      return true;
    }

    let marker = list[2];
    if (/^\d+[.)]$/.test(marker)) {
      marker = String(Number.parseInt(marker, 10) + 1) + marker.slice(-1);
    }

    const contentStart = line.text.length - list[3].length;
    const contentOffset = selection.head - line.from - contentStart;
    const bold = /^\*\*(.*)\*\*$/.exec(list[3]);
    const inlineCode = list[3].length >= 2 && list[3].charAt(0) === tick && list[3].charAt(list[3].length - 1) === tick;
    const nextPrefix = list[1] + marker + ' ';

    if (bold && contentOffset > 2 && contentOffset < list[3].length - 2) {
      const left = list[3].slice(2, contentOffset);
      const right = list[3].slice(contentOffset, -2);
      const insert = line.text.slice(0, contentStart) + '**' + left + '**\n' + nextPrefix + right;
      view.dispatch({
        changes: { from: line.from, to: line.to, insert },
        selection: { anchor: line.from + insert.indexOf('\n') + 1 + nextPrefix.length },
        scrollIntoView: true
      });
      return true;
    }

    if (inlineCode && contentOffset > 1 && contentOffset < list[3].length - 1) {
      const left = list[3].slice(1, contentOffset);
      const right = list[3].slice(contentOffset, -1);
      const insert = line.text.slice(0, contentStart) + tick + left + tick + '\n' + nextPrefix + right;
      view.dispatch({
        changes: { from: line.from, to: line.to, insert },
        selection: { anchor: line.from + insert.indexOf('\n') + 1 + nextPrefix.length },
        scrollIntoView: true
      });
      return true;
    }

    const prefix = '\n' + nextPrefix;
    view.dispatch({
      changes: { from: selection.head, insert: prefix },
      selection: { anchor: selection.head + prefix.length },
      scrollIntoView: true
    });
    return true;
  }

  const blockPrefix = /^(?:\s*#{1,6}\s+|>\s?)/.exec(line.text);
  const contentStart = blockPrefix ? blockPrefix[0].length : 0;
  const content = line.text.slice(contentStart);
  const offset = selection.head - line.from - contentStart;
  const bold = /^\*\*(.*)\*\*$/.exec(content);
  const inlineCode = content.length >= 2 && content.charAt(0) === tick && content.charAt(content.length - 1) === tick;

  if (bold && offset > 2 && offset < content.length - 2) {
    const left = content.slice(2, offset);
    const right = content.slice(offset, -2);
    const insert = line.text.slice(0, contentStart) + '**' + left + '**\n' + right;
    view.dispatch({
      changes: { from: line.from, to: line.to, insert },
      selection: { anchor: line.from + insert.indexOf('\n') + 1 },
      scrollIntoView: true
    });
    return true;
  }

  if (inlineCode && offset > 1 && offset < content.length - 1) {
    const left = content.slice(1, offset);
    const right = content.slice(offset, -1);
    const insert = line.text.slice(0, contentStart) + tick + left + tick + '\n' + right;
    view.dispatch({
      changes: { from: line.from, to: line.to, insert },
      selection: { anchor: line.from + insert.indexOf('\n') + 1 },
      scrollIntoView: true
    });
    return true;
  }

  return false;
}

function applyFormat(view, format) {
  const selection = view.state.selection.main;
  const hasSelection = !selection.empty;
  const line = view.state.doc.lineAt(selection.head);
  const blockPrefix = !hasSelection ? /^(?:\s*(?:#{1,6}|[-*+]|\d+[.)])\s+|>\s?)/.exec(line.text) : null;
  const from = hasSelection ? selection.from : line.from + (blockPrefix ? blockPrefix[0].length : 0);
  const to = hasSelection ? selection.to : line.to;
  const selected = view.state.sliceDoc(from, to);
  let insert = selected;
  let anchor = from;

  if (format === 'divider') {
    const divider = /^\s*(?:---+|___+|\*\*\*+)\s*$/.test(line.text);
    const value = divider ? '' : '---';
    view.dispatch({
      changes: { from: line.from, to: line.to, insert: value },
      selection: { anchor: line.from + value.length },
      scrollIntoView: true
    });
    view.focus();
    return;
  }

  if (format === 'bold' || format === 'inline_code') {
    const marker = format === 'bold' ? '**' : '`';
    const alreadyWrapped = selected.startsWith(marker) && selected.endsWith(marker) && selected.length >= marker.length * 2;
    if (alreadyWrapped) {
      insert = selected.slice(marker.length, selected.length - marker.length);
      anchor = from + Math.max(0, Math.min(selected.length - marker.length * 2, selection.head - from - marker.length));
    } else if (selected.length === 0 && format === 'bold') {
      insert = '****';
      anchor = from + 2;
    } else if (selected.length === 0) {
      insert = '``';
      anchor = from + 1;
    } else {
      insert = marker + selected + marker;
      anchor = from + marker.length + (hasSelection ? selected.length : selected.length);
    }
  } else if (format === 'heading' || format === 'numbered' || format === 'bulleted' || format === 'quote') {
    const prefixes = { heading: '### ', numbered: '1. ', bulleted: '- ', quote: '> ' };
    const prefix = prefixes[format];
    const current = line.text;
    const existing = format === 'heading' ? /^#{1,6}\s+/.exec(current)
      : format === 'numbered' ? /^\d+[.)]\s+/.exec(current)
      : format === 'bulleted' ? /^[-*+]\s+/.exec(current)
      : /^>\s?/.exec(current);
    if (existing && (format !== 'heading' || existing[0] === prefix)) {
      insert = current.slice(existing[0].length);
      anchor = line.from + Math.max(0, selection.head - line.from - existing[0].length);
    } else {
      insert = prefix + current;
      anchor = line.from + prefix.length + Math.max(0, selection.head - line.from);
    }
    view.dispatch({
      changes: { from: line.from, to: line.to, insert },
      selection: { anchor },
      scrollIntoView: true
    });
    view.focus();
    return;
  } else if (format === 'code_block') {
    const fence = /^\s*```/.test(line.text);
    if (fence) {
      view.dispatch({ changes: { from: line.from, to: line.to, insert: '' }, selection: { anchor: line.from }, scrollIntoView: true });
    } else {
      const block = '```\n' + (line.text || '') + '\n```';
      view.dispatch({ changes: { from: line.from, to: line.to, insert: block }, selection: { anchor: line.from + 4 + line.text.length }, scrollIntoView: true });
    }
    view.focus();
    return;
  } else {
    return;
  }

  view.dispatch({
    changes: { from, to, insert },
    selection: { anchor },
    scrollIntoView: true
  });
  view.focus();
}


function create({ textarea, host, onChange }) {
  let applyingExternalValue = false;
  const toolbar = textarea.closest('[data-text-mode]')?.querySelector('[data-details-toolbar]');
  const view = new EditorView({
    state: EditorState.create({
      doc: textarea.value || '',
      extensions: [
        history(),
        markdown(),
        keymap.of([{ key: 'Enter', run: continueList }, ...defaultKeymap, ...historyKeymap]),
        EditorView.perLineTextDirection.of(true),
        livePreview,
        EditorView.updateListener.of(update => {
          if (update.docChanged) {
            textarea.value = update.state.doc.toString();
            if (!applyingExternalValue && typeof onChange === 'function') onChange(textarea.value);
          }
          if (update.docChanged || update.selectionSet) syncToolbar(update.view, toolbar);
        }),
        EditorView.theme({
          '&': { direction: 'auto' },
          '.cm-content': { minHeight: '0', padding: '.85rem', fontFamily: 'inherit' },
          '.cm-scroller': { height: '100%', maxHeight: 'none', overflow: 'auto', fontFamily: 'inherit' },
          '.cm-line': { paddingInline: '.15rem' }
        })
      ]
    }),
    parent: host
  });
  syncToolbar(view, toolbar);
  if (toolbar) {
    toolbar.addEventListener('mousedown', event => {
      if (event.target.closest('button')) event.preventDefault();
    });
  }
  textarea.classList.add('is-code-editor-enhanced');
  return {
    setValue(value) {
      const next = String(value || '');
      if (view.state.doc.toString() !== next) {
        applyingExternalValue = true;
        try {
          view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: next } });
        } finally {
          applyingExternalValue = false;
        }
      }
      textarea.value = next;
    },
    focus() { view.focus(); },
    applyFormat(format) { applyFormat(view, format); }
  };
}

window.ZayanPostDetailsEditor = { create };
