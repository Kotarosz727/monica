<?php

namespace App\Services\DavClient;

use Illuminate\Support\Arr;
use App\Services\BaseService;
use function Safe\preg_replace;
use App\Models\Account\AddressBook;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use App\Http\Controllers\DAVClient\Dav\Client;
use App\Models\Account\AddressBookSubscription;
use App\Http\Controllers\DAVClient\AddressBookGetter;

class AddAddressBook extends BaseService
{
    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'account_id' => 'required|integer|exists:accounts,id',
            'user_id' => 'required|integer|exists:users,id',
            'base_uri' => 'required|string|url',
            'username' => 'required|string',
            'password' => 'required|string',
        ];
    }

    /**
     * Add a new Adress Book.
     *
     * @param array $data
     * @param GuzzleClient|null $httpClient
     * @return AddressBookSubscription|null
     */
    public function execute(array $data, GuzzleClient $httpClient = null): ?AddressBookSubscription
    {
        $this->validate($data);

        $addressBookData = $this->getAddressBookData($data, $httpClient);

        $lastAddressBook = AddressBook::where('account_id', $data['account_id'])
            ->orderBy('id', 'desc')
            ->first();

        $lastId = 0;
        if ($lastAddressBook) {
            $lastId = intval(preg_replace('/\w+(\d+)/i', '$1', $lastAddressBook->name));
        }
        $nextAddressBookName = 'contacts'.($lastId + 1);

        $addressBook = AddressBook::create([
            'account_id' => $data['account_id'],
            'user_id' => $data['user_id'],
            'name' => $nextAddressBookName,
            'description' => $addressBookData['name'],
        ]);
        $subscription = AddressBookSubscription::create([
            'account_id' => $data['account_id'],
            'user_id' => $data['user_id'],
            'username' => $data['username'],
            'address_book_id' => $addressBook->id,
            'uri' => $addressBookData['uri'],
            'capabilities' => $addressBookData['capabilities'],
        ]);
        $subscription->password = $data['password'];
        $subscription->save();

        return $subscription;
    }

    private function getAddressBookData(array $data, ?GuzzleClient $httpClient): ?array
    {
        try {
            $client = $this->getClient($data, $httpClient);

            return (new AddressBookGetter($client))->getAddressBookData();
        } catch (ClientException $e) {
            Log::error(__CLASS__.' getAddressBookBaseUri: '.$e->getMessage(), [$e]);
        }

        return null;
    }

    private function getClient(array $data, ?GuzzleClient $client): Client
    {
        $settings = Arr::only($data, [
            'base_uri',
            'username',
            'password',
        ]);

        return new Client($settings, $client);
    }
}
