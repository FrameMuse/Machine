async function DataLoader($url, $data = null) {
    axios.get($url, {
        params: $data,
      })
      .then(function (response) {
        console.log(response);
        return response['data'][0];
      })
      .catch(function (error) {
        console.log(error);
      })
}

async function bide($interval, $function) {
  return new Promise(function (resolve, reject) {
    var $result;
    if ($result == null) {
      setInterval(function ($fuunction) {
        $result = $function();
      }, $interval);
    } else {
      resolve($result);
    }
  });
}