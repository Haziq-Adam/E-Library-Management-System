(function(){
'use strict';

const HOURS = window.__SP_HOURS || [];
const overlay   = document.getElementById('slotModalOverlay');
const grid      = document.getElementById('modalSlotGrid');
const confirmBtn= document.getElementById('modalConfirmBtn');
const cancelBtn = document.getElementById('modalCancelBtn');
const closeBtn  = document.getElementById('modalClose');
const fBid      = document.getElementById('modalBid');
const fNewTime  = document.getElementById('modalNewTime');
const elRoom    = document.getElementById('modalRoom');
const elDate    = document.getElementById('modalDate');
const elCurTime = document.getElementById('modalCurrentTime');
const selInfo   = document.getElementById('spSelInfo');

let selectedSlot = null, currentSlot = null;

const SECTIONS = [
  {label:'Pagi', icon:'fa-sun', from:8, to:11},
  {label:'Tengahari', icon:'fa-cloud-sun', from:12, to:14},
  {label:'Petang', icon:'fa-cloud', from:15, to:18},
  {label:'Malam', icon:'fa-moon', from:19, to:22}
];

document.querySelectorAll('.btn-edit-slot').forEach(btn => {
  btn.addEventListener('click', function(){
    fBid.value = this.dataset.bid;
    currentSlot = this.dataset.current;
    selectedSlot = null;
    fNewTime.value = '';
    confirmBtn.disabled = true;
    selInfo.className = 'sp-selection-info';
    selInfo.innerHTML = '<i class="fa-regular fa-circle-dot"></i> Pilih slot masa baru';

    elRoom.textContent = this.dataset.room;
    elDate.textContent = formatDate(this.dataset.date);
    const ch = parseInt(currentSlot,10);
    elCurTime.textContent = currentSlot + ' – ' + String(ch+1).padStart(2,'0') + ':00';

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    loadSlots(this.dataset.room, this.dataset.date);
  });
});

function closeModal(){
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}
closeBtn.addEventListener('click', closeModal);
cancelBtn.addEventListener('click', closeModal);
overlay.addEventListener('click', e => { if(e.target===overlay) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape' && overlay.classList.contains('open')) closeModal(); });

async function loadSlots(room, date){
  grid.innerHTML = '<div class="sp-loading"><div class="sp-spinner"></div>Memuatkan slot...</div>';
  let occupied = {};
  try {
    const res = await fetch('booking_slots.php?room='+encodeURIComponent(room)+'&date='+encodeURIComponent(date));
    if(!res.ok) throw 0;
    const d = await res.json();
    (d.occupied||[]).forEach(s => { occupied[s] = d.owners?.[s] ?? null; });
  } catch(e) { occupied = {}; }
  renderGrid(date, occupied);
}

function renderGrid(date, occupied){
  const today = new Date().toISOString().split('T')[0];
  const curH  = new Date().getHours();
  const isToday = (date === today);
  grid.innerHTML = '';

  SECTIONS.forEach(sec => {
    for(let hr = sec.from; hr <= sec.to; hr++){
      const h = String(hr).padStart(2,'0')+':00';
      if(!HOURS.includes(h)) continue;
      const btn = document.createElement('button');
      btn.type = 'button';

      let st = 'free';
      if(isToday && hr <= curH) st = 'past';
      else if(h === currentSlot) st = 'current';
      else if(occupied[h] !== undefined){
        st = occupied[h]?.is_you ? 'mine' : 'taken';
      }
      if(selectedSlot === h) st = 'sel';

      btn.className = 'sp-slot s-'+st;
      const endH = String(hr+1).padStart(2,'0')+':00';
      let inner = '<span class="sp-t">'+h+'</span>';

      if(st==='taken' && occupied[h]?.nama) inner += '<span class="sp-tag">'+occupied[h].nama.split(' ')[0]+'</span>';
      else if(st==='current') inner += '<span class="sp-tag">◄ Semasa</span>';
      else if(st==='mine') inner += '<span class="sp-tag">Anda</span>';
      else inner += '<span class="sp-tag">'+endH+'</span>';

      btn.innerHTML = inner;

      if(st==='free'||st==='sel'){
        btn.addEventListener('click', () => selectSlot(h, date, occupied));
      }
      grid.appendChild(btn);
    }
  });
}

function selectSlot(h, date, occupied){
  selectedSlot = (selectedSlot===h) ? null : h;
  fNewTime.value = selectedSlot || '';
  confirmBtn.disabled = !selectedSlot;

  if(selectedSlot){
    const endH = String(parseInt(selectedSlot,10)+1).padStart(2,'0')+':00';
    selInfo.className = 'sp-selection-info has-sel';
    selInfo.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Tukar ke <strong>'+selectedSlot+' – '+endH+'</strong>';
  } else {
    selInfo.className = 'sp-selection-info';
    selInfo.innerHTML = '<i class="fa-regular fa-circle-dot"></i> Pilih slot masa baru';
  }
  renderGrid(date, occupied);
}

function formatDate(d){
  const m = ['Jan','Feb','Mac','Apr','Mei','Jun','Jul','Ogo','Sep','Okt','Nov','Dis'];
  const p = d.split('-');
  return parseInt(p[2],10)+' '+m[parseInt(p[1],10)-1]+' '+p[0];
}
})();
