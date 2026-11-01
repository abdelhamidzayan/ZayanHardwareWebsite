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
  const activeLine = view.state.doc.lineAt(view.state.selection.main.head).number;
  let inFence = false;
  for (let n = 1; n <= view.state.doc.lines; n += 1) {
    const line = view.state.doc.line(n);
    const value = line.text;
    const fence = /^\s*```/.test(value);
    if (n === activeLine) {
      if (fence) inFence = !inFence;
      continue;
    }
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
    const heading = /^(#{1,6})\s+(.+)$/.exec(value);
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
      ranges.push(Decoration.mark({ class: 'cm-live-preview-heading cm-live-preview-h' + heading[1].length }).range(line.from + prefix, line.to));
    } else if (quote) {
      ranges.push(Decoration.replace({}).range(line.from, line.from + quote[0].length));
      ranges.push(Decoration.line({ class: 'cm-live-preview-quote' }).range(line.from));
    } else if (bullet || ordered) {
      const prefix = bullet || ordered;
      ranges.push(Decoration.replace({}).range(line.from, line.from + prefix[0].length));
      ranges.push(Decoration.line({ class: 'cm-live-preview-list' }).range(line.from));
    }
    const inline = /\*\*(.+?)\*\*|`([^`]+)`/g;
    let match;
    while ((match = inline.exec(value)) !== null) {
      const token = match[0];
      const width = token.startsWith('**') ? 2 : 1;
      const from = line.from + match.index;
      ranges.push(Decoration.replace({}).range(from, from + width));
      ranges.push(Decoration.replace({}).range(from + token.length - width, from + token.length));
      ranges.push(Decoration.mark({ class: width === 2 ? 'cm-live-preview-bold' : 'cm-live-preview-inline-code' }).range(from + width, from + token.length - width));
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

function applyFormat(view, format) {
  const selection = view.state.selection.main;
  const selected = view.state.sliceDoc(selection.from, selection.to);
  const line = view.state.doc.lineAt(selection.from);
  const pairs = {
    bold: ['**', '**', 'نص'],
    inline_code: ['`', '`', 'code'],
    heading: ['### ', '', 'عنوان'],
    numbered: ['1. ', '', 'عنصر'],
    bulleted: ['- ', '', 'عنصر'],
    quote: ['> ', '', 'ملاحظة'],
    divider: ['\n---\n', '', ''],
    code_block: ['\n```\n', '\n```', 'code']
  };
  const pair = pairs[format];
  if (!pair) return;
  let from = selection.from;
  let to = selection.to;
  let insert = pair[0] + (selected || pair[2]) + pair[1];
  if (['heading', 'numbered', 'bulleted', 'quote'].includes(format)) {
    from = line.from;
    to = line.from;
    insert = pair[0];
  }
  view.dispatch({ changes: { from, to, insert }, selection: { anchor: from + insert.length }, scrollIntoView: true });
  view.focus();
}

function positionToolbar(toolbar) {
  if (!toolbar || !window.visualViewport) return;
  const viewport = window.visualViewport;
  const offset = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
  toolbar.style.setProperty('--editor-keyboard-offset', offset + 'px');
}

function create({ textarea, host, onChange }) {
  let applyingExternalValue = false;
  const view = new EditorView({
    state: EditorState.create({
      doc: textarea.value || '',
      extensions: [
        history(),
        markdown(),
        keymap.of([...defaultKeymap, ...historyKeymap]),
        EditorView.perLineTextDirection.of(true),
        livePreview,
        EditorView.updateListener.of(update => {
          if (update.docChanged) {
            textarea.value = update.state.doc.toString();
            if (!applyingExternalValue && typeof onChange === 'function') onChange(textarea.value);
          }
        }),
        EditorView.theme({
          '&': { direction: 'auto' },
          '.cm-content': { minHeight: '12em', padding: '.85rem', fontFamily: 'inherit' },
          '.cm-scroller': { maxHeight: '12em', overflow: 'auto', fontFamily: 'inherit' },
          '.cm-line': { paddingInline: '.15rem' }
        })
      ]
    }),
    parent: host
  });
  const toolbar = textarea.closest('[data-text-mode]')?.querySelector('[data-details-toolbar]');
  if (toolbar) {
    toolbar.addEventListener('mousedown', event => {
      if (event.target.closest('button')) event.preventDefault();
    });
    const refresh = () => positionToolbar(toolbar);
    window.visualViewport?.addEventListener('resize', refresh);
    window.visualViewport?.addEventListener('scroll', refresh);
    window.addEventListener('resize', refresh);
    refresh();
  }
  textarea.classList.add('is-code-editor-enhanced');
  return {
    setValue(value) {
      const next = String(value || '');
      if (view.state.doc.toString() !== next) {
        applyingExternalValue = true;
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: next } });
        applyingExternalValue = false;
      }
      textarea.value = next;
    },
    focus() { view.focus(); },
    applyFormat(format) { applyFormat(view, format); }
  };
}

window.ZayanPostDetailsEditor = { create };
