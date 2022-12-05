<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="/ajax.js"></script>
<script>
    var data = bide(1000, function() {
        DataLoader("/counter.php", {value: 30})
    });
    data.then(function($value) {
        alert($value);
    });
</script>