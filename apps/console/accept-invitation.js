const API_BASE=localStorage.getItem('wonderos_api_base')||'http://localhost:8080';
const form=document.querySelector('#accept-form');
const message=document.querySelector('#message');
const token=new URLSearchParams(window.location.search).get('token')||'';

function show(text,success=false){message.hidden=false;message.textContent=text;message.classList.toggle('success',success);}

form.addEventListener('submit',async event=>{
  event.preventDefault();
  const data=Object.fromEntries(new FormData(form));
  if(!token)return show('This invitation link is incomplete.');
  if(data.password!==data.confirmation)return show('The passwords do not match.');
  try{
    const response=await fetch(`${API_BASE}/v1/invitations/accept`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token,password:data.password})});
    const payload=await response.json();
    if(!response.ok||!payload.success)throw new Error(payload.error?.message||'The invitation could not be accepted.');
    form.innerHTML=`<p class="eyebrow">Account ready</p><h2>Welcome to WonderOS, ${String(payload.data.display_name).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}.</h2><p>Your account has been created. This invitation cannot be used again.</p><a class="primary" style="display:inline-block;text-decoration:none" href="./login.html">Sign in</a>`;
  }catch(error){show(error.message);}
});