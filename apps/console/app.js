const API_BASE = localStorage.getItem('wonderos_api_base') || 'http://localhost:8080';
const workspace = document.querySelector('#workspace');
const search = document.querySelector('#search');
const dialog = document.querySelector('#entity-dialog');
const form = document.querySelector('#entity-form');
const errorBox = document.querySelector('#form-error');

const remembered = JSON.parse(localStorage.getItem('wonderos_entities') || '[]');

class ApiError extends Error {
  constructor(payload, status) {
    super(payload.error?.message || 'The request failed.');
    this.code = payload.error?.code || 'REQUEST_FAILED';
    this.status = status;
    this.details = payload.error || {};
  }
}

function remember(entity) {
  const next = [entity, ...remembered.filter(item => item.wonder_id !== entity.wonder_id)].slice(0, 30);
  remembered.splice(0, remembered.length, ...next);
  localStorage.setItem('wonderos_entities', JSON.stringify(next));
}

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
}

function renderEntity(entity, notice = '') {
  remember(entity);
  workspace.innerHTML = `
    ${notice ? `<div class="card" style="margin-bottom:18px"><strong>${escapeHtml(notice)}</strong></div>` : ''}
    <div class="entity-grid">
      <article>
        <p class="eyebrow">Canonical entity</p>
        <h1 class="entity-title">${escapeHtml(entity.canonical_name)}</h1>
        <p class="status">${escapeHtml(entity.status)}</p>
        <div class="card" style="margin-top:24px">
          <h2>Identity</h2>
          <div class="meta-grid">
            <div class="meta-item"><span>Wonder ID</span>${escapeHtml(entity.wonder_id)}</div>
            <div class="meta-item"><span>Revision</span>${escapeHtml(entity.revision)}</div>
            <div class="meta-item"><span>Family</span>${escapeHtml(entity.family)}</div>
            <div class="meta-item"><span>Type</span>${escapeHtml(entity.type)}</div>
            <div class="meta-item"><span>Slug</span>${escapeHtml(entity.slug)}</div>
            <div class="meta-item"><span>Confidence</span>${Math.round(Number(entity.confidence) * 100)}%</div>
          </div>
        </div>
      </article>
      <aside class="card">
        <p class="eyebrow">Genesis status</p>
        <h2>Knowledge foundation</h2>
        <p>This record exists independently of WordPress. Relationships, claims, sources and editorial assets will attach here as Genesis expands.</p>
      </aside>
    </div>`;
}

function renderResults(query, entities) {
  if (!entities.length) {
    workspace.innerHTML = `<div class="card"><p class="eyebrow">No canonical match</p><h2>Nothing matches “${escapeHtml(query)}”</h2><p>Create a new entity only after checking spelling, aliases and broader terms.</p><button id="create-from-search">Create “${escapeHtml(query)}”</button></div>`;
    document.querySelector('#create-from-search').addEventListener('click', () => openCreate({canonical_name: query}));
    return;
  }

  workspace.innerHTML = `<div class="card"><p class="eyebrow">Canonical search</p><h2>${entities.length} result${entities.length === 1 ? '' : 's'} for “${escapeHtml(query)}”</h2><div id="search-results"></div></div>`;
  const results = document.querySelector('#search-results');
  entities.forEach(entity => {
    const button = document.createElement('button');
    button.type = 'button';
    button.style.display = 'block';
    button.style.width = '100%';
    button.style.textAlign = 'left';
    button.style.marginTop = '12px';
    button.innerHTML = `<strong>${escapeHtml(entity.canonical_name)}</strong><br><small>${escapeHtml(entity.wonder_id)} · ${escapeHtml(entity.family)} · ${escapeHtml(entity.type)}</small>`;
    button.addEventListener('click', () => renderEntity(entity));
    results.appendChild(button);
  });
}

function renderError(message) {
  workspace.innerHTML = `<div class="card error-card"><p class="eyebrow">WonderOS could not complete this action</p><h2>${escapeHtml(message)}</h2><p>Confirm the API is running at ${escapeHtml(API_BASE)}.</p></div>`;
}

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {'Content-Type':'application/json', ...(options.headers || {})}
  });
  const payload = await response.json();
  if (!response.ok || !payload.success) throw new ApiError(payload, response.status);
  return payload.data;
}

async function openEntity(id) {
  workspace.innerHTML = '<div class="card">Opening canonical entity…</div>';
  try { renderEntity(await request(`/v1/entities/${encodeURIComponent(id.toUpperCase())}`)); }
  catch (error) { renderError(error.message); }
}

async function searchEntities(query) {
  workspace.innerHTML = '<div class="card">Searching canonical knowledge…</div>';
  try { renderResults(query, await request(`/v1/entities?query=${encodeURIComponent(query)}`)); }
  catch (error) { renderError(error.message); }
}

function openCreate(defaults = {}) {
  form.reset();
  form.elements.canonical_name.value = defaults.canonical_name || '';
  form.elements.family.value = defaults.family || '';
  form.elements.type.value = defaults.type || '';
  form.elements.confidence.value = defaults.confidence || '0.50';
  errorBox.hidden = true;
  dialog.showModal();
  form.elements.canonical_name.focus();
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  errorBox.hidden = true;
  const data = Object.fromEntries(new FormData(form));
  data.confidence = Number(data.confidence);
  try {
    const entity = await request('/v1/entities', {method:'POST', body:JSON.stringify(data)});
    dialog.close();
    renderEntity(entity);
  } catch (error) {
    if (error.code === 'ENTITY_ALREADY_EXISTS' && error.details.existing_entity) {
      dialog.close();
      renderEntity(error.details.existing_entity, 'An existing canonical record was opened instead of creating a duplicate.');
      return;
    }
    errorBox.textContent = error.message;
    errorBox.hidden = false;
  }
});

search.addEventListener('keydown', event => {
  if (event.key !== 'Enter') return;
  const query = search.value.trim();
  if (!query) return;
  /^WND-[A-Z]{3}-\d{6}$/i.test(query) ? openEntity(query) : searchEntities(query);
});

document.addEventListener('keydown', event => {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    search.focus();
    search.select();
  }
});

document.querySelector('#new-entity').addEventListener('click', () => openCreate());
document.querySelector('#create-barn-owl').addEventListener('click', () => openCreate({canonical_name:'Barn Owl', family:'Living Things', type:'Bird', confidence:'0.90'}));
document.querySelector('#open-by-id').addEventListener('click', () => openEntity('WND-ENT-000001'));
document.querySelector('#close-dialog').addEventListener('click', () => dialog.close());
document.querySelector('#cancel-dialog').addEventListener('click', () => dialog.close());
