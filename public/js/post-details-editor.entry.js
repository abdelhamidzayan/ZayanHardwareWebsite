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
    const inline = /\*\*(.*?)\*\*|\*([^*\n]+?)\*|`(.*?)`/g;
    let match;
    while ((match = inline.exec(value)) !== null) {
      const token = match[0];
      const width = token.startsWith('**') ? 2 : 1;
      const from = line.from + match.index;
      ranges.push(Decoration.replace({}).range(from, from + width));
      ranges.push(Decoration.replace({}).range(from + token.length - width, from + token.length));
      if (token.length > width * 2) ranges.push(Decoration.mark({ class: match[3] !== undefined ? 'cm-live-preview-inline-code' : 'cm-live-preview-bold' }).range(from + width, from + token.length - width));
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
    const bold = /^(\*\*|\*)(.*)\1$/.exec(list[3]);
    const inlineCode = list[3].length >= 2 && list[3].charAt(0) === tick && list[3].charAt(list[3].length - 1) === tick;
    const nextPrefix = list[1] + marker + ' ';

    if (bold && contentOffset > bold[1].length && contentOffset < list[3].length - bold[1].length) {
      const left = list[3].slice(bold[1].length, contentOffset);
      const right = list[3].slice(contentOffset, -bold[1].length);
      const insert = line.text.slice(0, contentStart) + bold[1] + left + bold[1] + '\n' + nextPrefix + right;
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
  const bold = /^(\*\*|\*)(.*)\1$/.exec(content);
  const inlineCode = content.length >= 2 && content.charAt(0) === tick && content.charAt(content.length - 1) === tick;

  if (bold && offset > bold[1].length && offset < content.length - bold[1].length) {
    const left = content.slice(bold[1].length, offset);
    const right = content.slice(offset, -bold[1].length);
    const insert = line.text.slice(0, contentStart) + bold[1] + left + bold[1] + '\n' + right;
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

function create({ textarea, host, onChange }) {
  let applyingExternalValue = false;
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
    focus() { view.focus(); }
  };
}

window.ZayanPostDetailsEditor = { create };
