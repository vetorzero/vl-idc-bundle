# vl-idc-bundle

Bundle Symfony para integrar um sistema à Central de Identidade Vetor Zero (vl-idc).

- login via OpenID Connect (provider para o `league/oauth2-client`) e verificação do id_token;
- tokens máquina-a-máquina: pedir (`client_credentials`) e validar os recebidos;
- cliente da API da central;
- receptor dos avisos de mudança da central (webhooks);
- receptor do [OIDC Back-Channel Logout](https://openid.net/specs/openid-connect-backchannel-1_0.html).

Requer PHP 8.4+ e Symfony 7.4 ou 8.1+.


## Instalação

O pacote não está no Packagist. Adicione o repositório ao `composer.json` do projeto:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/vetorzero/vl-idc-bundle" }
]
```

```sh
composer require vetorzero/vl-idc-bundle
```

Registre o bundle, se o Flex não fizer isso sozinho:

```php
// config/bundles.php
VetorZero\VlIdcBundle\VlIdcBundle::class => ['all' => true],
```


## Configuração

```yaml
# config/packages/vl_idc.yaml
vl_idc:
    issuer: '%env(VL_IDC_BASE_URL)%'
    client_id: '%env(VL_IDC_CLIENT_ID)%'
    client_secret: '%env(VL_IDC_CLIENT_SECRET)%'
```

- `issuer`: a URL da central. O resto (endpoints, chaves) vem do discovery em
  `{issuer}/.well-known/openid-configuration` e fica em cache.
- `client_id` e `client_secret`: o client deste sistema, cadastrado na central.

Os serviços usam o `http_client`, o `cache.app`, o `event_dispatcher` e o `logger` do projeto, que
vêm do FrameworkBundle (o `http_client` precisa do `symfony/http-client` instalado).

As rotas dos receptores (webhook e back-channel logout) são opcionais:

```yaml
# config/routes/vl_idc.yaml
vl_idc:
    resource: '@VlIdcBundle/config/routes.php'
    prefix: /webhooks/idc
    trailing_slash_on_root: false
```

Com esse prefixo, as URLs a cadastrar no client da central são:

- webhook: `https://<host>/webhooks/idc`
- `backchannel_logout_uri`: `https://<host>/webhooks/idc/backchannel-logout`

As duas precisam ser https e públicas: a central não segue redirect nem chama rede privada. Deixe
esses caminhos fora do firewall de login, porque quem chama é a central e a autenticação é a
assinatura do token.


## Uso

### Login

O `VlIdcProvider` é um provider do `league/oauth2-client`. Com o
[KnpUOAuth2ClientBundle](https://github.com/knpuniversity/oauth2-client-bundle):

```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
    clients:
        vl-idc:
            type: generic
            provider_class: VetorZero\VlIdcBundle\OAuth\VlIdcProvider
            client_class: KnpU\OAuth2ClientBundle\Client\OAuth2PKCEClient
            client_id: '%env(VL_IDC_CLIENT_ID)%'
            client_secret: '%env(VL_IDC_CLIENT_SECRET)%'
            provider_options:
                baseUrl: '%env(VL_IDC_BASE_URL)%'
            redirect_route: app_auth_vl_idc_check
```

O client na central precisa do grant `authorization_code` com PKCE e do redirect URI dessa rota.

Depois do login, o `IdTokenVerifier::verify()` confere o `id_token` da resposta e devolve um
`IdToken` com o `sub` (a pessoa) e o `sid` (a sessão da central). Guarde o `sid` se quiser
encerrar a sessão local num back-channel logout.

### Tokens máquina-a-máquina

Para chamar outro sistema, peça um token com o client id dele como audience:

```php
$token = $serviceTokenProvider->getToken('client-id-do-destino');
```

O token fica em cache até pouco antes de vencer. Se o destino recusar, descarte com
`forget('client-id-do-destino')` e peça outro. Sem audience, o token serve para a API da própria
central. O client precisa do grant `client_credentials`.

Para receber chamadas de outros sistemas, valide o bearer token:

```php
$callerClientId = $serviceTokenValidator->validate($jwt);
```

Ele confere a assinatura, o emissor e se a audience inclui este client, e devolve o client id de
quem chamou. Um token inválido lança `InvalidTokenException`.

### API da central

```php
$user = $apiClient->getUser($sub); // id, name, email, picture
```

O `ApiClient` usa um token máquina-a-máquina do próprio client e, se receber 401, tenta de novo uma
vez com um token novo. Os erros da API viram subclasses de `ApiException`
(`NotFoundException`, `AccessDeniedException`, `RateLimitedException`, ...).

### Avisos de mudança (webhook)

A central avisa quando algo muda (ex.: `urn:vl-idc:event:user.updated`). O receptor verifica o
token e despacha um `SecurityEventReceived` para cada aviso:

```php
#[AsEventListener]
public function __invoke(SecurityEventReceived $received): void
{
    $event = $received->event; // id, type, subject, occurredAt, own
}
```

- O aviso não traz os dados do alvo: busque o estado atual na API.
- `own` indica que a mudança foi feita por este próprio client, via API.
- O listener roda de forma síncrona. Uma exceção vira 5xx, e a central manda o mesmo aviso de novo
  depois, com o mesmo `id`.
- O evento de verificação da central é respondido pelo próprio receptor e não é despachado.

### Back-channel logout

Quando uma sessão da central acaba, o receptor verifica o logout token e despacha um
`BackchannelLogoutReceived`:

```php
#[AsEventListener]
public function __invoke(BackchannelLogoutReceived $received): void
{
    $token = $received->token; // sub e/ou sid
}
```

- Com `sid`, encerre as sessões locais abertas naquela sessão da central.
- Só com `sub`, encerre todas as sessões da pessoa (ela foi desativada na central).
- Uma exceção no listener vira 5xx, e a central retenta por até ~24h com outro token. O listener
  precisa aguentar o mesmo logout mais de uma vez.


## Desenvolvimento

```sh
composer install
vendor/bin/phpunit
```

A CI roda a suíte com PHP 8.4 + Symfony 7.4, PHP 8.5 + Symfony 8.1 e com as versões mais baixas
aceitas pelo `composer.json`.

Versões seguem [semver](https://semver.org/lang/pt-BR/) e saem como tags (`v1.2.3`).


## Licença

[MIT](LICENSE).
