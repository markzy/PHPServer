BIN=/var/www/html/mc-benchmark/mc-benchmark
payload=32
iterations=$1
#if [[ $iterations -gt 1]]
#then
#       echo "haha"
#       echo $iterations
#fi
keyspace=100000
MCSET=''
MCGET=''
RDSET=''
RDGET=''
x='#'
for clients in 1 5 10 20 30 40 50 60 70 80 90 100 200 300
do
    printf "progress:[%-16s]%d clients...\r" $x $clients
    MCSETSPEED=0
    MCGETSPEED=0
    RDSETSPEED=0
    RDGETSPPED=0
    for dummy in 0 1 2
    do
        S=$($BIN -n $iterations -r $keyspace -d $payload -c $clients | grep 'per second')
        SET=$(echo "$S" | head -1 | cut -f 1 -d '.')
        GET=$(echo "$S" | tail -1 | cut -f 1 -d '.')
        if [[ $SET -gt $MCSETSPEED ]]
        then
                MCSETSPEED=$SET
        fi
        if [[ $GET -gt $MCGETSPEED ]]
        then
                MCGETSPEED=$GET
        fi
        S=$(redis-benchmark -t set,get -n $iterations -r $keyspace -d $payload -c $clients | grep 'per second')
        SET=$(echo "$S" | head -1 | cut -f 1 -d '.')
        GET=$(echo "$S" | tail -1 | cut -f 1 -d '.')
        if [[ $SET -gt $RDSETSPEED ]]
        then
                RDSETSPEED=$SET
        fi
        if [[ $GET -gt $RDGETSPEED ]]
        then
                RDGETSPEED=$GET
        fi
      done
      MCSET=$MCSET"$clients,$MCSETSPEED "
      MCGET=$MCGET"$clients,$MCGETSPEED "
      RDSET=$RDSET"$clients,$RDSETSPEED "
      RDGET=$RDGET"$clients,$RDGETSPEED "
      x=#$x
  done
  mysql -uroot -pmarkng -e"INSERT INTO myblog.benchmark (redisset,redisget,memset,memget) values ('$RDSET','$RDGET','$MCSET','$MCGET')"
  x=#$x
  printf "progress:[%-16s]benchmark finished\r" $x
  echo




  BIN=/var/www/html/mc-benchmark/mc-benchmark
  payload=32
  iterations=$1
  keyspace=100000
  MCSET=''
  MCGET=''
  RDSET=''
  RDGET=''
  x='#'
  for clients in 1 5 10 20 30
  do
      printf "progress:[%-16s]%d clients...\r" $x $clients
      MCSETSPEED=0
      MCGETSPEED=0
      RDSETSPEED=0
      RDGETSPPED=0
      for dummy in 0 1 2
      do
          S=$($BIN -n $iterations -r $keyspace -d $payload -c $clients | grep 'per second')
          SET=$(echo "$S" | head -1 | cut -f 1 -d '.')
          GET=$(echo "$S" | tail -1 | cut -f 1 -d '.')
          if [[ $SET -gt $MCSETSPEED ]]
          then
                  MCSETSPEED=$SET
          fi
          if [[ $GET -gt $MCGETSPEED ]]
          then
                  MCGETSPEED=$GET
          fi
          S=$(redis-benchmark -t set,get -n $iterations -r $keyspace -d $payload -c $clients | grep 'per second')
          SET=$(echo "$S" | head -1 | cut -f 1 -d '.')
          GET=$(echo "$S" | tail -1 | cut -f 1 -d '.')
          if [[ $SET -gt $RDSETSPEED ]]
          then
                  RDSETSPEED=$SET
          fi
          if [[ $GET -gt $RDGETSPEED ]]
          then
                  RDGETSPEED=$GET
          fi
        done
        MCSET=$MCSET"$clients,$MCSETSPEED "
        MCGET=$MCGET"$clients,$MCGETSPEED "
        RDSET=$RDSET"$clients,$RDSETSPEED "
        RDGET=$RDGET"$clients,$RDGETSPEED "
        x=#$x
    done
    mysql -uroot -pmarkng -e"INSERT INTO myblog.benchmark (redisset,redisget,memset,memget) values ('$RDSET','$RDGET','$MCSET','$MCGET')"
    x=#$x
    printf "progress:[%-16s]benchmark finished\r" $x
    echo
