const API_BASE = localStorage.getItem('wonderos_api_base') || 'http://localhost:8080';
const form = document.querySelector('#lens-form');
const list = document.querySelector('#lens-list');
const message = document.querySelector('#studio-message');
const lifecycle = document.querySelector('#lifecycle');
const historyPanel = document.querySelector('#history-panel');
const previewPanel = document.querySelector('#preview-panel');
const confidence = document.querySelector('#confidence');
const confidenceOutput = document.querySelector('#confidence-output');
let current = null;

function credentials() {
  const editor = document.querySelector('#editor').value.trim();
  const key = document.querySelector('#editor-key').value;
  localStorage.setItem('wonderos_editor', editor);
  sessionStorage.setItem('wonderos_editor_key', key);
  return {'X-WonderOS-Editor': editor, 'X-WonderOS-Editor-Key': key};
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
}

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {'Content-Type':'application/json', ...credentials(), ...(options.headers || {})}
  });
  const payload = await response.json();
  if (!response.ok || !payload.success) throw new Error(payload.error?.message || 'The request failed.');
  return payload.data;
}

function csv(value) {
  return value.split(',').map(item => item.trim().toLowerCase()).filter(Boolean);
}

function payload() {
  const data = Object.fromEntries(new FormData(form));
  return {
    slug: data.slug.trim(),
    name: data.name.trim(),
    description: data.description.trim(),
    types: csv(data.types),
    statuses: csv(data.statuses),
    minimum_confidence: Number(data.minimum_confidence),
    ...(current ? {expected_revision: current.revision} : {})
  };
}

function setMessage(text, success = false) {
  message.textContent = text;
  message.classList.toggle('success', success);
  message.hidden = false;
}

function fill(lens) {
  current = lens;
  form.elements.slug.value = lens.slug;
  form.elements.slug.readOnly = true;
  form.elements.name.value = lens.name;
  form.elements.description.value = lens.description;
  form.elements.types.value = lens.types.join(', ');
  form.elements.statuses.value = lens.statuses.join(', ');
  form.elements.minimum_confidence.value = lens.minimum_confidence;
  confidenceOutput.textContent = Number(lens.minimum_confidence).toFixed(2);
  lifecycle.textContent = `${lens.status} · r${lens.revision}`;
  document.querySelector('#form-title').textContent = lens.name;
  loadHistory();
}

function newLens() {
  current = null;
  form.reset();
  form.elements.slug.readOnly = false;
  form.elements.minimum_confidence.value = 0.5;
  confidenceOutput.textContent = '0.50';
  lifecycle.textContent = 'new';
  document.querySelector('#form-title').textContent = 'Create a purposeful graph view';
  historyPanel.textContent = 'Save the draft to begin its revision history.';
  previewPanel.textContent = 'Save the draft before previewing it.';
  message.hidden = true;
}

async function loadLenses() {
  list.innerHTML = '<p class="muted">Loading lenses…</p>';
  try {
    const lenses = await request('/v1/editorial-lens-studio');
    list.innerHTML = '';
    lenses.forEach(lens => {
      const button = document.createElement('button');
      button.className = `lens-row${current?.slug === lens.slug ? ' active' : ''}`;
      button.innerHTML = `<strong>${escapeHtml(lens.name)}</strong><small>${escapeHtml(lens.status)} · r${lens.revision}</small>`;
      button.addEventListener('click', () => fill(lens));
      list.appendChild(button);
    });
    if (!lenses.length) list.innerHTML = '<p class="muted">No lenses exist yet.</p>';
  } catch (error) {
    list.innerHTML = `<p class="form-error">${escapeHtml(error.message)}</p>`;
  }
}

async function loadHistory() {
  if (!current) return;
  try {
    const revisions = await request(`/v1/editorial-lenses/${encodeURIComponent(current.slug)}/revisions`);
    historyPanel.innerHTML = revisions.map(item => `<div class="history-item"><strong>Revision ${item.revision}</strong><span>${escapeHtml(item.changed_by)} · ${escapeHtml(item.changed_at)}</span><small>${escapeHtml(item.snapshot.status)}</small></div>`).join('') || '<p class="muted">No revisions found.</p>';
  } catch (error) {
    historyPanel.textContent = error.message;
  }
}

async function mutate(action) {
  if (!current) return setMessage('Save this lens before changing its lifecycle.');
  try {
    const lens = await request(`/v1/editorial-lenses/${encodeURIComponent(current.slug)}/${action}`, {
      method:'POST', body:JSON.stringify({expected_revision: current.revision})
    });
    fill(lens);
    setMessage(`Lens ${action}d successfully.`, true);
    loadLenses();
  } catch (error) { setMessage(error.message); }
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  try {
    const body = payload();
    const lens = await request(current ? `/v1/editorial-lenses/${encodeURIComponent(current.slug)}` : '/v1/editorial-lenses', {
      method: current ? 'PUT' : 'POST', body: JSON.stringify(body)
    });
    fill(lens);
    setMessage(current ? 'Revision saved.' : 'Draft created.', true);
    loadLenses();
  } catch (error) { setMessage(error.message); }
});

document.querySelector('#preview').addEventListener('click', async () => {
  if (!current) return setMessage('Save this lens before previewing it.');
  previewPanel.textContent = 'Building preview…';
  try {
    const result = await request(`/v1/editorial-lenses/${encodeURIComponent(current.slug)}/preview`, {
      method:'POST', body:JSON.stringify({root_wonder_id:document.querySelector('#preview-root').value.trim(), depth:2, max_nodes:50})
    });
    previewPanel.innerHTML = `<p><strong>${result.preview.nodes.length}</strong> nodes · <strong>${result.preview.edges.length}</strong> edges</p>${result.preview.nodes.map(node => `<div class="graph-node"><strong>${escapeHtml(node.canonical_name)}</strong><small>${escapeHtml(node.wonder_id)} · distance ${node.distance}</small></div>`).join('')}`;
  } catch (error) { previewPanel.textContent = error.message; }
});

confidence.addEventListener('input', () => confidenceOutput.textContent = Number(confidence.value).toFixed(2));
document.querySelector('#activate').addEventListener('click', () => mutate('activate'));
document.querySelector('#deprecate').addEventListener('click', () => mutate('deprecate'));
document.querySelector('#new-lens').addEventListener('click', newLens);
document.querySelector('#refresh').addEventListener('click', loadLenses);
document.querySelector('#connect').addEventListener('click', loadLenses);

document.querySelector('#editor').value = localStorage.getItem('wonderos_editor') || '';
document.querySelector('#editor-key').value = sessionStorage.getItem('wonderos_editor_key') || '';
newLens();
if (document.querySelector('#editor-key').value) loadLenses();
