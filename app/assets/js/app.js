document.addEventListener('DOMContentLoaded',()=>{
  const main=document.getElementById('app-main');
  document.querySelectorAll('[data-view]').forEach(a=>{
    a.addEventListener('click',e=>{
      e.preventDefault();
      loadView(a.getAttribute('data-view'));
    });
  });
  function loadView(v){
    switch(v){
      case 'invoices':
        main.innerHTML='<h2>Invoices</h2><p>Fetching latest …</p>';
        fetch('api/router.php?action=invoices')
          .then(r=>r.json())
          .then(j=>{
            if(!j.ok) { main.innerHTML='Error: '+j.error; return;}
            const rows=j.invoices.map(i=>`<tr><td>${i.id}</td><td>${i.invoice_number||''}</td><td>${i.invoice_date||''}</td><td>${i.grand_total||''}</td></tr>`).join('');
            main.innerHTML='<h2>Invoices</h2><table><thead><tr><th>ID</th><th>No</th><th>Date</th><th>Total</th></tr></thead><tbody>'+rows+'</tbody></table>';
          }).catch(err=> main.innerHTML='Fetch error '+err);
        break;
      case 'journal':
        main.innerHTML='<h2>Journal</h2><p>Use API: POST journal_post.</p>';
        break;
      case 'reports':
        main.innerHTML='<h2>Reports</h2><ul><li>Trial Balance: <code>api/router.php?action=trial_balance</code></li><li>GST Summary: <code>api/router.php?action=gst_summary&from=YYYY-MM-DD&to=YYYY-MM-DD</code></li></ul>';
        break;
      default:
        main.innerHTML='<h2>Dashboard</h2><p>Welcome. Explore navigation links.</p>';
    }
  }
});
