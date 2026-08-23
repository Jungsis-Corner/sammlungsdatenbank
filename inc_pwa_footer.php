<script>
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("/sammlung/sw.js").catch(console.error);
    });
  }
</script>