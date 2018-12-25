# use curl march multi request
# example：
# $requestData = [
#   [....请求数据1],
#   [....请求数据2],
# ];
# $callback 为当前回调函数，因为批量请求所有的结果为了实现处理给了回调函数
# $concurrenceCount 为当前可以并行数量
# $http = new HttpHelper( $requestData, $callback, $concurrenceCount );
# $http::post();  //当然也可以是$http::get();
#
#
#callback：
#
#public function callback($response, $info, $error, $request){...//函数体}
