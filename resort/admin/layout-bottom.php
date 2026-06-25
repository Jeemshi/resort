</div></div></div><script>
// Mobile Sidebar Toggle
document.addEventListener('click', function(e){
    const sb = document.getElementById('adminSidebar');
    if(sb && !sb.contains(e.target) && !e.target.closest('#sidebarToggle')){
        sb.classList.remove('open');
    }
});

// Auto-dismiss notification banners
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { 
        a.style.opacity='0'; 
        a.style.transition='opacity .5s'; 
        setTimeout(()=>a.remove(), 500); 
    }, 5000);
});
</script>
</body>
</html>