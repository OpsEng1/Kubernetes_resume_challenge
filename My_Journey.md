### Step 2

Step 2 I placed the environment variables in the actual Docker image but in Kubernetes I found out you can just place them in the actual deployment yaml file.

I built and pushed the image to Docker Hub. When I built the file i realised the Dockerfile was also included so promtly added that to the .dockerignore file and rebuilt.

### Step 3 

I had no idea how to set up GKE on the free tier. This video helped immensely: https://www.youtube.com/watch?v=hxpGC19PzwI

I was also about to install the Google CLI but decided to use the "Run in Cloud Shell" option. this saved me installing on my end.

### Step 4 and 5

Didn't run into much issues here. 

### Step 6

I don't know much php but a quick search online allowed to to find out the syntax and the need to point to a separate dark them .css file which i needed to create. Again searching online allowed me to find an example quickly. It didn't need to look nice, it just needed to work as it's a K8s challenge , not php! :)

I also wanted to test this using Docker before I pushed out. I documented how i achieved that here:

If you want to test using Docker, add the feature to the existing Dockerfile

```yaml
# Use php:7.4-apache as the base image
FROM php:7.4-apache

# Install mysqli extension for PHP
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy the application source code into the container
COPY . /var/www/html/

# Set environment variables for database connection
ENV DB_HOST=mysql-service
ENV DB_USER=ecomuser
ENV DB_PASSWORD=ecompassword
ENV DB_NAME=ecomdb

# Update database connection strings to point to a Kubernetes service named mysql-service
RUN sed -i 's/172.20.1.101/mysql-service/g' /var/www/html/index.php

# Expose port 80 to allow traffic to the web server
EXPOSE 80

# Set environment variable to enable dark mode
ENV FEATURE_DARK_MODE=true

```

then run the following commands to bring up the Database

```bash
docker network create testnetwork
docker run -d -p 80:80 --network=testnetwork ecom-web-dark:v1
docker run -d --name mysql-service --network testnetwork -e MYSQL_ROOT_PASSWORD=XXXXXX -v /<dir>/db-load-script.sql:/docker-entrypoint-initdb.d/db-load-script.sql mariadb:latest
```

I also used the command line to create the configmap:

### Configmap

```yaml
kubectl create configmap feature-toggle-config --from-literal=FEATURE_DARK_MODE=true --dry-run=client -o yaml
apiVersion: v1
data:
  FEATURE_DARK_MODE: "true"
kind: ConfigMap
metadata:
  name: feature-toggle-config
```

### Step 7

Scaling only allowed me to scale up to 4 pods , most likely due to the limitation on the GKE free version but i was able to scale quickly using this command 

```bash
kubectl scale deployment/ecomweb --replicas=6
deployment.apps/ecomweb scaled
```

### Step 8

```bash
docker build -t <Docker Username>/ecom-web:v2 .
docker push <Docker Username>/ecom-web:v2

# update deployment.yaml with v2
kubectl apply -f deployment.yaml
kubectl get pods
$ kubectl rollout status deployment/ecomweb 
deployment "ecomweb" successfully rolled out

# Once ready then access the url
```

### Step 9

```bash
kubectl rollout undo deployment/ecomweb
kubectl rollout status deployment/ecomweb
```

### Step 10

```bash
# Since i'm using GKE , i'm limited to 4 Pods
kubectl autoscale deployment ecomweb --cpu-percent=50 --min=2 --max=4
# Install Apache Bench
sudo apt-get install apache2-utils -y
ab -n 1000 -c 100 http://<publicIP obtained from the external IP of service>/#
```

### Step 11

I had some trouble hitting the path /. In the end I create seperate paths for the liveness and readiness probes and this worked fine.

```php
# Created the following php files to test readiness and liveness. Would need to update the rebuild the docker image. Place these php files in the same places as index.php

[live.php]
<?php
// Check the status of your application and return a success message if everything is working fine
$status = "OK";

// Set the response headers
header("Content-Type: text/plain");

// Output the status message
echo $status;
?>

[ready.php]
<?php
// Check if your application is ready to serve traffic
$isReady = true;

// Set the HTTP response code based on the readiness status
http_response_code($isReady ? 200 : 503);

// Set the response headers
header("Content-Type: text/plain");

// Output the readiness status message
echo $isReady ? "Ready" : "Not Ready";
?>
```

```bash
#Troubleshooted the pod which had issues connecting to path / using kubectl describe on pod (Can see probes failed) and you can test if the response is quick using the following command:

date;kubectl exec -it ecomweb-6449d585df-nrbgd -c ecom-web -- curl -I http://<ip of Pod>:80/live.php;date
```

### Step 12

Had trouble where even though image pull policy is set to always, i would still have an older image. In the end I appended the Hash at the end of image tag to download that specific image. Think it was cached somewhere as I logged onto the pod itself and could see the old files. The hash worked perfectly!

```bash
spec:
      containers:
      - image: kryten6/ecom-web:dark@sha256:edfe6c35016e2a5bd0ded279c0de433b046a37bafa7028b53aa8060769a180db
```

