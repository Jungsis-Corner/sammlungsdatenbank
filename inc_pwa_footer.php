<script>
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("/sammlung/sw.js", { updateViaCache: "none" }).catch(console.error);   // sw.js nie aus dem HTTP-Cache (Strato liefert es mit 30 Tagen)
    });
  }
</script>